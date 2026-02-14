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
        try {
            $result = self::extractWithAI($content, $metadata);

            return ServiceResult::success([
                'people' => $result['people'] ?? [],
                'tasks' => $result['tasks'] ?? [],
                'facts' => $result['facts'] ?? [],
                'relationships' => $result['relationships'] ?? [],
                'summary' => $result['summary'] ?? self::generateSimpleSummary($content),
                'importance' => $result['importance'] ?? 0.5,
            ]);
        } catch (\Exception $e) {
            // Fallback to simple extraction
            return ServiceResult::success([
                'people' => self::extractPeopleSimple($content),
                'tasks' => self::extractTasksSimple($content),
                'facts' => [],
                'relationships' => [],
                'summary' => self::generateSimpleSummary($content),
                'importance' => 0.5,
            ]);
        }
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

        if (!$response->successful()) {
            throw new \Exception("OpenAI API request failed: " . $response->body());
        }

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

    /**
     * Simple people extraction (fallback)
     */
    private static function extractPeopleSimple(string $content): array
    {
        // Basic name extraction using capitalized words
        preg_match_all('/\b[A-Z][a-z]+ [A-Z][a-z]+\b/', $content, $matches);
        return array_unique($matches[0] ?? []);
    }

    /**
     * Simple task extraction (fallback)
     */
    private static function extractTasksSimple(string $content): array
    {
        $tasks = [];
        $keywords = ['TODO', 'ASAP', 'action item', 'please', 'can you', 'need to', 'should'];

        foreach ($keywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                $sentences = preg_split('/[.!?]+/', $content);
                foreach ($sentences as $sentence) {
                    if (stripos($sentence, $keyword) !== false) {
                        $tasks[] = trim($sentence);
                    }
                }
            }
        }

        return array_slice($tasks, 0, 5); // Max 5 tasks
    }

    /**
     * Generate simple summary
     */
    private static function generateSimpleSummary(string $content): string
    {
        $sentences = preg_split('/[.!?]+/', $content);
        $firstSentence = trim($sentences[0] ?? '');

        if (strlen($firstSentence) > 200) {
            $firstSentence = substr($firstSentence, 0, 197) . '...';
        }

        return $firstSentence;
    }
}
