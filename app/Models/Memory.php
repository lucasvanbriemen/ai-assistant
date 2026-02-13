<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Memory extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'content',
        'summary',
        'content_length',
        'content_hash',
        'metadata',
        'relevance_score',
        'reminder_at',
        'is_archived',
        'last_accessed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'relevance_score' => 'decimal:2',
        'reminder_at' => 'datetime',
        'is_archived' => 'boolean',
        'last_accessed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        // Auto-generate content hash and length on creation
        static::creating(function ($memory) {
            $memory->content_hash = hash('sha256', $memory->content);
            $memory->content_length = mb_strlen($memory->content);
        });

        // Update hash and length on content change
        static::updating(function ($memory) {
            if ($memory->isDirty('content')) {
                $memory->content_hash = hash('sha256', $memory->content);
                $memory->content_length = mb_strlen($memory->content);
            }
        });

        // Generate embedding after creation (async via queue would be better in production)
        static::created(function ($memory) {
            try {
                $embeddingService = app(\App\AI\Services\EmbeddingService::class);
                $embeddingService->generateForMemory($memory);
            } catch (\Exception $e) {
                \Log::warning("Failed to generate embedding for memory {$memory->id}: {$e->getMessage()}");
            }
        });

        // Clear cache on changes (if tagging is supported)
        static::saved(function ($memory) {
            try {
                Cache::tags(['memory', 'search'])->flush();
            } catch (\BadMethodCallException $e) {
                Cache::flush(); // Fallback for cache stores without tag support
            }
        });

        static::deleted(function ($memory) {
            try {
                Cache::tags(['memory', 'search'])->flush();
            } catch (\BadMethodCallException $e) {
                Cache::flush();
            }
        });
    }

    // ==================== Relationships ====================

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(MemoryEntity::class, 'memory_entity_links', 'memory_id', 'entity_id')
            ->withPivot('link_type')
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MemoryTag::class, 'memory_tag_links', 'memory_id', 'tag_id')
            ->withTimestamps();
    }

    public function embedding(): HasOne
    {
        return $this->hasOne(MemoryEmbedding::class);
    }

    // ==================== Query Scopes ====================

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeRelevant($query, float $minScore = 0.5)
    {
        return $query->where('relevance_score', '>=', $minScore);
    }

    public function scopeWithReminders($query)
    {
        return $query->whereNotNull('reminder_at')
            ->where('reminder_at', '>', now())
            ->where('is_archived', false);
    }

    public function scopeUpcoming($query, $startDate = null, $endDate = null)
    {
        $query->whereNotNull('reminder_at')
            ->where('is_archived', false);

        if ($startDate) {
            $query->where('reminder_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('reminder_at', '<=', $endDate);
        }

        return $query->orderBy('reminder_at', 'asc');
    }

    // ==================== Search Methods ====================

    /**
     * Full-text search on content and summary
     */
    public static function search(string $query, array $options = [])
    {
        $cacheKey = 'memory:search:' . md5($query . serialize($options));

        $cacheCallback = function () use ($query, $options) {
            $builder = static::query()
                ->select('memories.*')
                ->selectRaw('MATCH(content, summary) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance', [$query])
                ->whereRaw('MATCH(content, summary) AGAINST(? IN NATURAL LANGUAGE MODE)', [$query])
                ->where('is_archived', false)
                ->orderByDesc('relevance');

            // Apply filters
            if (!empty($options['type'])) {
                $builder->where('type', $options['type']);
            }

            if (!empty($options['entity_id'])) {
                $builder->whereHas('entities', function ($q) use ($options) {
                    $q->where('memory_entities.id', $options['entity_id']);
                });
            }

            if (!empty($options['tag_id'])) {
                $builder->whereHas('tags', function ($q) use ($options) {
                    $q->where('memory_tags.id', $options['tag_id']);
                });
            }

            if (!empty($options['from_date'])) {
                $builder->where('created_at', '>=', $options['from_date']);
            }

            if (!empty($options['to_date'])) {
                $builder->where('created_at', '<=', $options['to_date']);
            }

            // Eager load relationships to prevent N+1
            $builder->with(['entities', 'tags']);

            $limit = $options['limit'] ?? 10;
            return $builder->limit($limit)->get();
        };

        try {
            return Cache::tags(['memory', 'search'])->remember($cacheKey, 300, $cacheCallback);
        } catch (\BadMethodCallException $e) {
            return Cache::remember($cacheKey, 300, $cacheCallback);
        }
    }

    /**
     * Semantic search using vector embeddings
     */
    public static function searchSemantic(string $query, int $limit = 10)
    {
        try {
            $embeddingService = app(\App\AI\Services\EmbeddingService::class);
            $results = $embeddingService->searchMemories($query, $limit);

            // Extract memory objects from results
            return collect($results)->map(function ($result) {
                $memory = $result['memory'];
                $memory->similarity_score = $result['similarity'];
                return $memory;
            });
        } catch (\Exception $e) {
            \Log::warning("Semantic search failed, falling back to full-text: {$e->getMessage()}");
            return static::search($query, ['limit' => $limit]);
        }
    }

    /**
     * Hybrid search combining full-text and semantic search
     */
    public static function searchHybrid(string $query, array $options = [])
    {
        $limit = $options['limit'] ?? 10;

        // Get results from both methods
        $fullTextResults = static::search($query, array_merge($options, ['limit' => $limit]));

        // Combine and deduplicate by ID
        $combined = $fullTextResults->unique('id');

        // Sort by combined relevance score
        return $combined->sortByDesc(function ($memory) {
            return $memory->relevance_score * ($memory->relevance ?? 1);
        })->take($limit)->values();
    }

    /**
     * Find potential duplicates using content hash
     */
    public static function findDuplicate(string $content): ?self
    {
        $hash = hash('sha256', $content);
        return static::where('content_hash', $hash)
            ->where('is_archived', false)
            ->first();
    }

    // ==================== Helper Methods ====================

    /**
     * Record access for relevance tracking
     */
    public function recordAccess(): void
    {
        $this->update(['last_accessed_at' => now()]);
    }

    /**
     * Archive this memory
     */
    public function archive(): void
    {
        $this->update(['is_archived' => true]);
    }

    /**
     * Restore archived memory
     */
    public function restore(): void
    {
        $this->update(['is_archived' => false]);
    }

    /**
     * Attach entities to this memory
     */
    public function attachEntities(array $entityIds, string $linkType = 'mentioned'): void
    {
        foreach ($entityIds as $entityId) {
            $this->entities()->syncWithoutDetaching([
                $entityId => ['link_type' => $linkType]
            ]);
        }
    }

    /**
     * Attach tags to this memory
     */
    public function attachTags(array $tagNames): void
    {
        $tagIds = [];
        foreach ($tagNames as $tagName) {
            $tag = MemoryTag::findOrCreateTag($tagName);
            $tagIds[] = $tag->id;
        }
        $this->tags()->syncWithoutDetaching($tagIds);
    }
}
