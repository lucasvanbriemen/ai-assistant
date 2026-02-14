<?php

namespace App\AI\Services;

use App\AI\Contracts\ServiceResult;
use Illuminate\Support\Facades\Http;

class AutoMemoryExtractionService
{
    /**
     * Extract structured information from content using AI
     */
    public static function extract(string $content, array $metadata = []): ServiceResult
    {
        $result = self::extractWithAI($content, $metadata);

        return ServiceResult::success([
            'people' => $result['people'] ?? [],
            'tasks' => $result['tasks'] ?? [],
            'facts' => $result['facts'] ?? [],
            'relationships' => $result['relationships'] ?? [],
            'summary' => $result['summary'] ?? '',
            'importance' => $result['importance'] ?? 0.5,
        ]);
    }

    /**
     * Extract information using OpenAI
     */
    private static function extractWithAI(string $content, array $metadata): array
    {
        $prompt = self::buildExtractionPrompt($content, $metadata);

        $response = Http::withToken(config('ai.openai.api_key'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert at extracting structured information from text. Return ONLY valid JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 500,
            ]);

        $result = $response->json();
        $extractedText = $result['choices'][0]['message']['content'] ?? '{}';

        return json_decode($extractedText, true) ?? [];
    }

    /**
     * Build extraction prompt for AI
     */
    private static function buildExtractionPrompt(string $content, array $metadata): string
    {
        $source = $metadata['source'] ?? 'unknown';

        return <<<PROMPT
Analyze this {$source} content and extract structured information.

Content:
{$content}

Extract and return as JSON:
{
  "people": ["Name 1", "Name 2"],
  "tasks": ["Task 1", "Task 2"],
  "facts": ["Fact 1", "Fact 2"],
  "relationships": [{"from": "Person", "to": "Company", "type": "works_at"}],
  "summary": "Brief summary in 1-2 sentences",
  "importance": 0.8
}

Return ONLY the JSON, no other text.
PROMPT;
    }
}
