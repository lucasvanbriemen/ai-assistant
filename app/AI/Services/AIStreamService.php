<?php

namespace App\AI\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIStreamService
{
    private const BASE_URL = 'https://api.openai.com/v1';
    private const MODEL = 'gpt-4o-mini';

    public static function streamResponse(string $message, array $conversationHistory): \Generator
    {
        // Build messages using existing AIService::buildMessages pattern
        $messages = self::buildMessages($message, $conversationHistory);

        $requestData = [
            'model' => self::MODEL,
            'messages' => $messages,
            'temperature' => 0.3,
            'max_tokens' => config('ai.max_tokens'),
            'stream' => true, // Enable streaming
        ];

        try {
            // Open streaming connection to OpenAI
            $response = Http::withToken(config('ai.openai.api_key'))
                ->timeout(config('ai.streaming.timeout', 60))
                ->withOptions(['stream' => true])
                ->post(self::BASE_URL . "/chat/completions", $requestData);

            if (!$response->successful()) {
                throw new \Exception('OpenAI API error: ' . $response->status());
            }

            $fullMessage = '';
            $body = $response->getBody();

            // Read and parse SSE stream
            while (!$body->eof()) {
                $line = self::readLine($body);
                if (empty($line) || $line === 'data: [DONE]') {
                    continue;
                }

                if (str_starts_with($line, 'data: ')) {
                    try {
                        $json = json_decode(substr($line, 6), true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            continue;
                        }

                        $delta = $json['choices'][0]['delta']['content'] ?? '';

                        if ($delta) {
                            $fullMessage .= $delta;
                            yield self::formatSSE('chunk', ['content' => $delta]);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Error parsing SSE chunk: ' . $e->getMessage());
                        continue;
                    }
                }
            }

            yield self::formatSSE('done', ['message' => $fullMessage]);
        } catch (\Exception $e) {
            Log::error('Streaming error: ' . $e->getMessage());
            yield self::formatSSE('error', ['message' => 'Streaming failed: ' . $e->getMessage()]);
        }
    }

    public static function formatSSE(string $event, array $data): string
    {
        return "event: {$event}\ndata: " . json_encode($data) . "\n\n";
    }

    private static function readLine($stream): string
    {
        $line = '';
        while (!$stream->eof()) {
            $char = $stream->read(1);
            if ($char === "\n") {
                break;
            }
            $line .= $char;
        }
        return trim($line);
    }

    private static function buildMessages(string $message, array $conversationHistory): array
    {
        $messages = [];

        // Add system prompt as the first message
        $systemPrompt = config('ai.system_prompt', 'You are a helpful AI assistant.');
        $today = date('l, F j, Y'); // e.g., "Friday, February 7, 2026"
        $systemPrompt .= "\n\nCurrent date and time: {$today}. Use this to interpret relative dates like 'last Friday', 'tomorrow', 'next week', etc.";

        $messages[] = [
            'role' => 'system',
            'content' => $systemPrompt,
        ];

        $historyToInclude = array_slice($conversationHistory, -(config('ai.max_history') * 2));

        foreach ($historyToInclude as $entry) {
            if (isset($entry['role']) && isset($entry['content'])) {
                $messages[] = $entry;
            }
        }

        // Add current message
        $messages[] = [
            'role' => 'user',
            'content' => $message,
        ];

        return $messages;
    }
}
