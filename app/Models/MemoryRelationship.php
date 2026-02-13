<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class MemoryRelationship extends Model
{
    protected $fillable = [
        'from_entity_id',
        'to_entity_id',
        'relationship_type',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        // Clear cache on changes (if tagging is supported)
        static::saved(function ($relationship) {
            try {
                Cache::tags(['relationships'])->flush();
            } catch (\BadMethodCallException $e) {
                Cache::flush();
            }
        });

        static::deleted(function ($relationship) {
            try {
                Cache::tags(['relationships'])->flush();
            } catch (\BadMethodCallException $e) {
                Cache::flush();
            }
        });
    }

    // ==================== Relationships ====================

    public function fromEntity(): BelongsTo
    {
        return $this->belongsTo(MemoryEntity::class, 'from_entity_id');
    }

    public function toEntity(): BelongsTo
    {
        return $this->belongsTo(MemoryEntity::class, 'to_entity_id');
    }

    // ==================== Static Methods ====================

    /**
     * Find or create a relationship between two entities
     */
    public static function findOrCreate(int $fromEntityId, int $toEntityId, string $type, array $metadata = []): self
    {
        $relationship = static::where('from_entity_id', $fromEntityId)
            ->where('to_entity_id', $toEntityId)
            ->where('relationship_type', $type)
            ->first();

        if ($relationship) {
            // Update metadata if provided
            if (!empty($metadata)) {
                $relationship->metadata = array_merge($relationship->metadata ?? [], $metadata);
                $relationship->save();
            }
            return $relationship;
        }

        return static::create([
            'from_entity_id' => $fromEntityId,
            'to_entity_id' => $toEntityId,
            'relationship_type' => $type,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get all relationships for an entity
     */
    public static function getForEntity(int $entityId, ?string $type = null)
    {
        $query = static::where('from_entity_id', $entityId)
            ->orWhere('to_entity_id', $entityId);

        if ($type) {
            $query->where('relationship_type', $type);
        }

        return $query->with(['fromEntity', 'toEntity'])->get();
    }
}
