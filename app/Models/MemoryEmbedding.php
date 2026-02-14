<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemoryEmbedding extends Model
{
    protected $fillable = [
        'memory_id',
        'embedding',
        'model',
        'dimensions',
        'magnitude',
    ];

    protected $casts = [
        'embedding' => 'array',
        'dimensions' => 'integer',
        'magnitude' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function memory()
    {
        return $this->belongsTo(Memory::class);
    }

    /**
     * Find similar embeddings using cosine similarity
     *
     * For scalability: Uses chunked processing if embeddings exceed threshold.
     * This prevents memory exhaustion with large datasets.
     */
    public static function findSimilar(array $queryEmbedding, int $limit = 10, float $minSimilarity = 0.5)
    {
        // Scalability safeguard: Check total embedding count first
        $totalEmbeddings = static::count();
        $maxEmbeddings = config('ai.max_embeddings_for_search', 5000);

        // If too many embeddings, use chunked processing
        if ($totalEmbeddings > $maxEmbeddings) {
            // Use chunked processing for better performance
            return static::findSimilarChunked($queryEmbedding, $limit, $minSimilarity);
        }

        // Original implementation for smaller datasets
        $embeddings = static::with('memory')->get();

        // Pre-compute query magnitude once
        $queryMagnitude = static::calculateMagnitude($queryEmbedding);

        $similarities = [];

        foreach ($embeddings as $embedding) {
            if (!$embedding->memory || $embedding->memory->is_archived) {
                continue;
            }

            // Use pre-computed magnitudes for faster calculation
            $similarity = static::calculateCosineSimilarity(
                $queryEmbedding,
                $embedding->embedding,
                $queryMagnitude,
                $embedding->magnitude
            );

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
     * Chunked processing for large embedding sets
     * Processes embeddings in batches to avoid memory exhaustion
     * Uses pre-computed magnitudes for faster calculations
     */
    private static function findSimilarChunked(array $queryEmbedding, int $limit = 10, float $minSimilarity = 0.5)
    {
        $chunkSize = 500;
        $topResults = [];

        // Pre-compute query magnitude once
        $queryMagnitude = static::calculateMagnitude($queryEmbedding);

        // Process embeddings in chunks
        static::with('memory')->chunk($chunkSize, function ($embeddings) use ($queryEmbedding, $queryMagnitude, $minSimilarity, &$topResults, $limit) {
            foreach ($embeddings as $embedding) {
                if (!$embedding->memory || $embedding->memory->is_archived) {
                    continue;
                }

                // Use pre-computed magnitudes for faster calculation
                $similarity = static::calculateCosineSimilarity(
                    $queryEmbedding,
                    $embedding->embedding,
                    $queryMagnitude,
                    $embedding->magnitude
                );

                if ($similarity >= $minSimilarity) {
                    $topResults[] = [
                        'memory' => $embedding->memory,
                        'similarity' => $similarity,
                    ];
                }
            }

            // Keep only top results to manage memory
            if (count($topResults) > $limit * 3) {
                usort($topResults, function ($a, $b) {
                    return $b['similarity'] <=> $a['similarity'];
                });
                $topResults = array_slice($topResults, 0, $limit * 2);
            }
        });

        // Final sort and trim
        usort($topResults, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        return array_slice($topResults, 0, $limit);
    }

    /**
     * Calculate magnitude (Euclidean norm) of an embedding vector
     */
    public static function calculateMagnitude(array $embedding): float
    {
        $sumOfSquares = 0;
        foreach ($embedding as $value) {
            $sumOfSquares += $value * $value;
        }
        return sqrt($sumOfSquares);
    }

    /**
     * Static helper for cosine similarity calculation
     * Optimized version using pre-computed magnitudes when available
     */
    public static function calculateCosineSimilarity(array $embedding1, array $embedding2, ?float $magnitude1 = null, ?float $magnitude2 = null): float
    {
        if (count($embedding1) !== count($embedding2)) {
            throw new \InvalidArgumentException('Embeddings must have the same dimensions');
        }

        $dotProduct = 0;

        for ($i = 0; $i < count($embedding1); $i++) {
            $dotProduct += $embedding1[$i] * $embedding2[$i];
        }

        // Use pre-computed magnitudes if provided, otherwise calculate
        if ($magnitude1 === null) {
            $magnitude1 = static::calculateMagnitude($embedding1);
        }
        if ($magnitude2 === null) {
            $magnitude2 = static::calculateMagnitude($embedding2);
        }

        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }
}
