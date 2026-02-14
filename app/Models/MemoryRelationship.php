<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function fromEntity(): BelongsTo
    {
        return $this->belongsTo(MemoryEntity::class, 'from_entity_id');
    }

    public function toEntity(): BelongsTo
    {
        return $this->belongsTo(MemoryEntity::class, 'to_entity_id');
    }

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
}
