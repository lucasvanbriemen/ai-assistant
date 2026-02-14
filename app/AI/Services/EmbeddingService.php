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

    /**
     * Generate embedding for a single text
     */
    public function generateEmbedding(string $text): array
    {
        // Check cache first using content hash
        $cacheKey = 'embedding:' . hash('sha256', $text);

        return Cache::remember($cacheKey, 86400, function () use ($text) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . config('ai.openai.api_key'),
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => self::MODEL,
                    'input' => $text,
                ]);

                if (!$response->successful()) {
                    throw new \Exception('OpenAI API error: ' . $response->body());
                }

                $data = $response->json();
                return $data['data'][0]['embedding'];

            } catch (\Exception $e) {
                throw $e;
            }
        });
    }

    /**
     * Generate embeddings for multiple texts in batch
     */
    public function generateBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('ai.openai.api_key'),
            'Content-Type' => 'application/json',
        ])
        ->timeout(60)
        ->post('https://api.openai.com/v1/embeddings', [
            'model' => self::MODEL,
            'input' => $texts,
        ]);

        $data = $response->json();

        // Extract embeddings in the same order as input
        $embeddings = [];
        foreach ($data['data'] as $item) {
            $embeddings[$item['index']] = $item['embedding'];
        }

        ksort($embeddings); // Ensure correct order
        return array_values($embeddings);
    }

    /**
     * Calculate cosine similarity between two embeddings
     */
    public function cosineSimilarity(array $embedding1, array $embedding2): float
    {
        return MemoryEmbedding::calculateCosineSimilarity($embedding1, $embedding2);
    }

    /**
     * Find similar memories using vector search
     */
    public function findSimilar(array $queryEmbedding, int $limit = 10, float $minSimilarity = 0.5): array
    {
        return MemoryEmbedding::findSimilar($queryEmbedding, $limit, $minSimilarity);
    }

    /**
     * Generate and store embedding for a memory
     */
    public function generateForMemory(Memory $memory): MemoryEmbedding
    {
        // Use summary if available and content is long, otherwise use content
        $text = $memory->summary ?: $memory->content;

        // Truncate if too long (OpenAI has token limits)
        if (strlen($text) > 8000) {
            $text = substr($text, 0, 8000);
        }

        $embedding = $this->generateEmbedding($text);

        // Store or update embedding
        return MemoryEmbedding::updateOrCreate(
            ['memory_id' => $memory->id],
            [
                'embedding' => $embedding,
                'model' => self::MODEL,
                'dimensions' => self::DIMENSIONS,
            ]
        );
    }

    /**
     * Search memories using semantic similarity
     */
    public function searchMemories(string $query, int $limit = 10, array $options = []): array
    {
        // Generate embedding for the query
        $queryEmbedding = $this->generateEmbedding($query);

        // Find similar memories
        $minSimilarity = $options['min_similarity'] ?? 0.5;
        $results = $this->findSimilar($queryEmbedding, $limit * 2, $minSimilarity);

        // Apply additional filters if provided
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

        // Return top N after filtering
        return array_slice($results, 0, $limit);
    }
}
