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

        return MemoryEmbedding::updateOrCreate(
            ['memory_id' => $memory->id],
            [
                'embedding' => $embedding,
                'model' => self::MODEL,
                'dimensions' => self::DIMENSIONS,
            ]
        );
    }

    public function searchMemories(string $query, int $limit = 10, array $options = []): array
    {
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
}
