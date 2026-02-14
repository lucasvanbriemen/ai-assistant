<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoryEmbedding extends Model
{
    protected $fillable = [
        'memory_id',
        'embedding',
        'model',
        'dimensions',
    ];

    protected $casts = [
        'embedding' => 'array',
        'dimensions' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class);
    }

    /**
     * Calculate cosine similarity between this embedding and another
     */
    public function cosineSimilarity(array $otherEmbedding): float
    {
        $embedding1 = $this->embedding;
        $embedding2 = $otherEmbedding;

        if (count($embedding1) !== count($embedding2)) {
            throw new \InvalidArgumentException('Embeddings must have the same dimensions');
        }

        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;

        for ($i = 0; $i < count($embedding1); $i++) {
            $dotProduct += $embedding1[$i] * $embedding2[$i];
            $magnitude1 += $embedding1[$i] * $embedding1[$i];
            $magnitude2 += $embedding2[$i] * $embedding2[$i];
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    /**
     * Find similar embeddings using cosine similarity
     */
    public static function findSimilar(array $queryEmbedding, int $limit = 10, float $minSimilarity = 0.5)
    {
        // Get all embeddings (in production, this should be optimized with a vector database)
        $embeddings = static::with('memory')->get();

        $similarities = [];

        foreach ($embeddings as $embedding) {
            if (!$embedding->memory || $embedding->memory->is_archived) {
                continue;
            }

            $similarity = static::calculateCosineSimilarity($queryEmbedding, $embedding->embedding);

            if ($similarity >= $minSimilarity) {
                $similarities[] = [
                    'memory' => $embedding->memory,
                    'similarity' => $similarity,
                ];
            }
        }

        // Sort by similarity descending
        usort($similarities, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        // Return top N results
        return array_slice($similarities, 0, $limit);
    }

    /**
     * Static helper for cosine similarity calculation
     */
    public static function calculateCosineSimilarity(array $embedding1, array $embedding2): float
    {
        if (count($embedding1) !== count($embedding2)) {
            throw new \InvalidArgumentException('Embeddings must have the same dimensions');
        }

        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;

        for ($i = 0; $i < count($embedding1); $i++) {
            $dotProduct += $embedding1[$i] * $embedding2[$i];
            $magnitude1 += $embedding1[$i] * $embedding1[$i];
            $magnitude2 += $embedding2[$i] * $embedding2[$i];
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }
}
