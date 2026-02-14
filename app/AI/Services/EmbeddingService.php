<?php

namespace App\AI\Services;

use App\Models\Memory;
use App\Models\MemoryEmbedding;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    private const MODEL = 'text-embedding-3-small';
    private const DIMENSIONS = 1536;

    public function generateEmbedding(string $text): array
    {
        // Check cache first using content hash
        $cacheKey = 'embedding:' . hash('sha256', $text);

        return Cache::remember($cacheKey, 86400, function () use ($text) {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('ai.openai.api_key'),
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => self::MODEL,
                'input' => $text,
            ]);

            $data = $response->json();
            return $data['data'][0]['embedding'];
        });
    }

    public function generateForMemory(Memory $memory): MemoryEmbedding
    {
        $text = $memory->summary ?: $memory->content;

        if (strlen($text) > 8000) {
            $text = substr($text, 0, 8000);
        }

        $embedding = $this->generateEmbedding($text);

        // Pre-compute magnitude for faster similarity calculations
        $magnitude = MemoryEmbedding::calculateMagnitude($embedding);

        return MemoryEmbedding::updateOrCreate(
            ['memory_id' => $memory->id],
            [
                'embedding' => $embedding,
                'model' => self::MODEL,
                'dimensions' => self::DIMENSIONS,
                'magnitude' => $magnitude,
            ]
        );
    }

    public function searchMemories(string $query, int $limit = 10, array $options = []): array
    {
        // Use hybrid search by default (full-text first, then semantic)
        $useHybrid = $options['hybrid'] ?? true;

        if ($useHybrid) {
            return $this->searchMemoriesHybrid($query, $limit, $options);
        }

        // Pure semantic search (original method)
        $queryEmbedding = $this->generateEmbedding($query);

        $minSimilarity = $options['min_similarity'] ?? 0.5;
        $results = MemoryEmbedding::findSimilar($queryEmbedding, $limit * 2, $minSimilarity);

        if (!empty($options['type'])) {
            $results = array_filter($results, function ($result) use ($options) {
                return $result['memory']->type === $options['type'];
            });
        }

        if (!empty($options['entity_id'])) {
            $results = array_filter($results, function ($result) use ($options) {
                return $result['memory']->entities->contains('id', $options['entity_id']);
            });
        }

        return array_slice($results, 0, $limit);
    }

    /**
     * Hybrid search: Full-text first to get candidates, then semantic for precision
     * This is MUCH faster than pure semantic search at scale
     */
    public function searchMemoriesHybrid(string $query, int $limit = 10, array $options = []): array
    {
        $candidateLimit = $options['candidate_limit'] ?? 100;

        // Step 1: Fast full-text search to get top candidates
        $candidates = \App\Models\Memory::search($query, array_merge($options, [
            'limit' => $candidateLimit
        ]));

        // If no candidates found, return empty
        if ($candidates->isEmpty()) {
            return [];
        }

        // Step 2: Semantic search on only the candidates
        $queryEmbedding = $this->generateEmbedding($query);
        $minSimilarity = $options['min_similarity'] ?? 0.5;

        $candidateIds = $candidates->pluck('id')->toArray();

        // Get embeddings only for candidates
        $embeddings = MemoryEmbedding::with('memory')
            ->whereIn('memory_id', $candidateIds)
            ->get();

        // Pre-compute query magnitude once
        $queryMagnitude = MemoryEmbedding::calculateMagnitude($queryEmbedding);

        $results = [];
        foreach ($embeddings as $embedding) {
            if (!$embedding->memory || $embedding->memory->is_archived) {
                continue;
            }

            $similarity = MemoryEmbedding::calculateCosineSimilarity(
                $queryEmbedding,
                $embedding->embedding,
                $queryMagnitude,
                $embedding->magnitude
            );

            if ($similarity >= $minSimilarity) {
                $results[] = [
                    'memory' => $embedding->memory,
                    'similarity' => $similarity,
                ];
            }
        }

        // Sort by similarity descending
        usort($results, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        return array_slice($results, 0, $limit);
    }
}
