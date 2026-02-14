<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MemoryEntity extends Model
{
    protected $fillable = [
        'user_id',
        'entity_type',
        'entity_subtype',
        'name',
        'description',
        'summary',
        'attributes',
        'mention_count',
        'last_mentioned_at',
        'is_active',
        'email',
        'phone',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'attributes' => 'array',
        'mention_count' => 'integer',
        'last_mentioned_at' => 'datetime',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
    }

    public function memories()
    {
        return $this->belongsToMany(Memory::class, 'memory_entity_links', 'entity_id', 'memory_id')
            ->withPivot('link_type')
            ->withTimestamps();
    }

    public function relationshipsFrom()
    {
        return $this->hasMany(MemoryRelationship::class, 'from_entity_id');
    }

    public function relationshipsTo()
    {
        return $this->hasMany(MemoryRelationship::class, 'to_entity_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('entity_type', $type);
    }

    public function scopeOfSubtype($query, string $subtype)
    {
        return $query->where('entity_subtype', $subtype);
    }

    public function scopePeople($query)
    {
        return $query->where('entity_type', 'person');
    }

    public function scopePlaces($query)
    {
        return $query->where('entity_type', 'place');
    }

    public function scopeOrganizations($query)
    {
        return $query->where('entity_type', 'organization');
    }

    /**
     * Scope: Current entities (no end_date or end_date in future)
     */
    public function scopeCurrent($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('end_date')
              ->orWhere('end_date', '>=', now()->toDateString());
        });
    }

    /**
     * Scope: Past entities (end_date in the past)
     */
    public function scopePast($query)
    {
        return $query->where('end_date', '<', now()->toDateString());
    }

    /**
     * Scope: Future entities (start_date in the future)
     */
    public function scopeFuture($query)
    {
        return $query->where('start_date', '>', now()->toDateString());
    }

    /**
     * Scope: Active during a specific date range
     */
    public function scopeActiveDuring($query, $startDate, $endDate = null)
    {
        $endDate = $endDate ?? $startDate;

        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->where(function ($q2) use ($startDate) {
                // Started before or on the start date (or no start date)
                $q2->where('start_date', '<=', $startDate)
                   ->orWhereNull('start_date');
            })->where(function ($q2) use ($endDate) {
                // Ended after or on the end date, or no end date (still active)
                $q2->where('end_date', '>=', $endDate)
                   ->orWhereNull('end_date');
            });
        });
    }

    /**
     * Full-text search on entity name and description
     */
    public static function searchEntities(string $query, array $options = [])
    {
        $cacheKey = 'entity:search:' . md5($query . serialize($options));

        $cacheCallback = function () use ($query, $options) {
            $builder = static::query()
                ->select('memory_entities.*')
                ->selectRaw('MATCH(name, description) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance', [$query])
                ->whereRaw('MATCH(name, description) AGAINST(? IN NATURAL LANGUAGE MODE)', [$query])
                ->where('is_active', true)
                ->orderByDesc('relevance')
                ->orderByDesc('mention_count');

            // Apply filters
            if (!empty($options['entity_type'])) {
                $builder->where('entity_type', $options['entity_type']);
            }

            if (!empty($options['entity_subtype'])) {
                $builder->where('entity_subtype', $options['entity_subtype']);
            }

            $limit = $options['limit'] ?? 10;
            return $builder->limit($limit)->get();
        };

        try {
            return Cache::tags(['entities', 'search'])->remember($cacheKey, 600, $cacheCallback);
        } catch (\BadMethodCallException $e) {
            return Cache::remember($cacheKey, 600, $cacheCallback);
        }
    }

    /**
     * Find entity by name (case-insensitive)
     */
    public static function findByName(string $name, ?string $type = null)
    {
        $query = static::where('name', 'LIKE', $name)
            ->where('is_active', true);

        if ($type) {
            $query->where('entity_type', $type);
        }

        return $query->first();
    }

    /**
     * Find or create an entity - UNIVERSAL for ANY entity type
     * Automatically extracts email/phone to columns, stores rest in JSON
     */
    public static function findOrCreateEntity(array $data): self
    {
        // Extract attributes from nested structure if present
        $attributes = $data['attributes'] ?? [];

        // Extract ONLY email and phone to dedicated columns (for indexing)
        $email = null;
        $phone = null;
        $jsonAttributes = [];

        foreach ($attributes as $key => $value) {
            $normalizedKey = strtolower(trim($key));

            // Check for email variations
            if (in_array($normalizedKey, ['email', 'mail', 'email_address', 'e-mail', 'work_email'])) {
                $email = $value;
            }
            // Check for phone variations
            elseif (in_array($normalizedKey, ['phone', 'phone_number', 'tel', 'telephone', 'mobile', 'cell', 'work_phone'])) {
                $phone = $value;
            }
            // Everything else goes to JSON (unlimited flexibility!)
            else {
                $jsonAttributes[$key] = $value;
            }
        }

        // Try to find existing entity - prioritize email matching over name matching
        $entity = null;

        // FIRST: Try to match by email if provided (email is unique identifier)
        if ($email !== null) {
            $entity = static::where('email', $email)
                ->where('entity_type', $data['entity_type'])
                ->where('is_active', true)
                ->first();

            // If found by email, update the name if it's more complete
            if ($entity && strlen($data['name']) > strlen($entity->name)) {
                $entity->name = $data['name'];
            }
        }

        // SECOND: Fall back to name matching if no email or no match found
        if ($entity === null) {
            $entity = static::where('name', 'LIKE', $data['name'])
                ->where('entity_type', $data['entity_type'])
                ->where('is_active', true)
                ->first();
        }

        if ($entity) {
            // Update existing entity
            $entity->recordMention();

            // Update email/phone if provided
            if ($email !== null) {
                $entity->email = $email;
            }
            if ($phone !== null) {
                $entity->phone = $phone;
            }

            // Merge new attributes with existing JSON
            if (!empty($jsonAttributes)) {
                $entity->mergeAttributes($jsonAttributes);
            }

            if ($data['description'] !== null) {
                $entity->description = $data['description'];
            }
            if ($data['entity_subtype'] !== null) {
                $entity->entity_subtype = $data['entity_subtype'];
            }

            // Update temporal fields if provided
            if ($data['start_date'] !== null) {
                $entity->start_date = $data['start_date'];
            }
            if ($data['end_date'] !== null) {
                $entity->end_date = $data['end_date'];
            }

            $entity->save();
            return $entity;
        }

        // Create new entity
        return static::create([
            'user_id' => $data['user_id'] ?? null,
            'entity_type' => $data['entity_type'],
            'entity_subtype' => $data['entity_subtype'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'email' => $email,
            'phone' => $phone,
            'attributes' => $jsonAttributes,
            'mention_count' => 1,
            'last_mentioned_at' => now(),
            'is_active' => true,
            // Temporal tracking
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
        ]);
    }

    /**
     * Record a mention of this entity
     */
    public function recordMention(): void
    {
        $this->increment('mention_count');
        $this->update(['last_mentioned_at' => now()]);
    }

    /**
     * Merge new attributes with existing ones
     */
    public function mergeAttributes(array $newAttributes): void
    {
        if (empty($newAttributes)) {
            return;
        }

        // Get current attributes from the JSON column (not Laravel's $attributes property)
        $current = $this->getAttributes()['attributes'] ?? [];
        if (is_string($current)) {
            $current = json_decode($current, true) ?? [];
        }

        $merged = array_merge($current, $newAttributes);

        // Update the attributes column explicitly
        $this->update(['attributes' => $merged]);
    }

    /**
     * Get all memories related to this entity
     */
    public function getAllMemories(int $limit = 50)
    {
        $cacheKey = "entity:{$this->id}:memories";

        $cacheCallback = function () use ($limit) {
            return $this->memories()
                ->where('is_archived', false)
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        };

        try {
            return Cache::tags(['entities', 'memory'])->remember($cacheKey, 600, $cacheCallback);
        } catch (\BadMethodCallException $e) {
            return Cache::remember($cacheKey, 600, $cacheCallback);
        }
    }

    /**
     * Get entity relationships with details
     */
    public function getRelationshipsWithDetails()
    {
        $cacheKey = "entity:{$this->id}:relationships";

        $cacheCallback = function () {
            $from = $this->relationshipsFrom()->with('toEntity')->get();
            $to = $this->relationshipsTo()->with('fromEntity')->get();

            return [
                'from' => $from,
                'to' => $to,
            ];
        };

        try {
            return Cache::tags(['entities', 'relationships'])->remember($cacheKey, 600, $cacheCallback);
        } catch (\BadMethodCallException $e) {
            return Cache::remember($cacheKey, 600, $cacheCallback);
        }
    }

    /**
     * Check if entity is currently active (temporal)
     */
    public function isCurrent(): bool
    {
        // No end date = still active
        if (!$this->end_date) {
            return true;
        }

        // Has end date - check if it's in the future
        return $this->end_date->isFuture() || $this->end_date->isToday();
    }

    /**
     * Check if entity is in the past
     */
    public function isPast(): bool
    {
        return $this->end_date && $this->end_date->isPast();
    }

    /**
     * Check if entity is in the future (hasn't started yet)
     */
    public function isFuture(): bool
    {
        return $this->start_date && $this->start_date->isFuture();
    }

    /**
     * Check if entity was/is/will be active during a specific date
     */
    public function isActiveDuring(\Carbon\Carbon $date): bool
    {
        // Check start date (if exists)
        if ($this->start_date && $date->isBefore($this->start_date)) {
            return false;
        }

        // Check end date (if exists)
        if ($this->end_date && $date->isAfter($this->end_date)) {
            return false;
        }

        return true;
    }

    /**
     * Archive this entity
     */
    public function archive(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Restore archived entity
     */
    public function restoreEntity(): void
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Get full entity details including memories and relationships
     */
    public function getFullDetails(int $memoryLimit = 20): array
    {
        // Get attributes from the JSON column, not Laravel's internal $attributes property
        $jsonAttrs = $this->getAttributes()['attributes'] ?? [];
        if (is_string($jsonAttrs)) {
            $jsonAttrs = json_decode($jsonAttrs, true) ?? [];
        }

        // Combine email/phone columns with JSON attributes
        $allAttributes = [];

        // Add email and phone from columns if present
        if ($this->email) {
            $allAttributes['email'] = $this->email;
        }
        if ($this->phone) {
            $allAttributes['phone'] = $this->phone;
        }

        // Merge with all JSON attributes (everything else)
        $allAttributes = array_merge($allAttributes, $jsonAttrs);

        return [
            'id' => $this->id,
            'type' => $this->entity_type,
            'subtype' => $this->entity_subtype,
            'name' => $this->name,
            'description' => $this->description,
            'summary' => $this->summary,
            'attributes' => $allAttributes,
            'mention_count' => $this->mention_count,
            'last_mentioned_at' => $this->last_mentioned_at?->toDateTimeString(),
            // Temporal tracking
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_current' => $this->isCurrent(),
            'is_past' => $this->isPast(),
            'is_future' => $this->isFuture(),
            'memories' => $this->getAllMemories($memoryLimit)->map(function ($memory) {
                return [
                    'id' => $memory->id,
                    'type' => $memory->type,
                    'content' => $memory->content,
                    'created_at' => $memory->created_at->toDateTimeString(),
                ];
            }),
            'relationships' => $this->getRelationshipsWithDetails(),
        ];
    }
}
