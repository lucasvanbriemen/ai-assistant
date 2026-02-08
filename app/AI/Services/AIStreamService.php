<?php

namespace App\AI\Services;

use App\AI\Core\PluginList;
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

        try {
            // Stream response from OpenAI
            $requestData = [
                'model' => self::MODEL,
                'messages' => $messages,
                'temperature' => 0.3,
                'max_tokens' => config('ai.max_tokens'),
                'tools' => PluginList::formatToolsForOpenAI(),
                'stream' => true,
            ];

            $response = Http::withToken(config('ai.openai.api_key'))
                ->timeout(config('ai.streaming.timeout', 60))
                ->withOptions(['stream' => true])
                ->post(self::BASE_URL . "/chat/completions", $requestData);

            if (!$response->successful()) {
                throw new \Exception('OpenAI API error: ' . $response->status());
            }

            $fullMessage = '';
            $body = $response->getBody();
            $hasToolCalls = false;
            $toolCalls = [];

            // Stream response and check for tool calls
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

                        // Check for tool calls in the stream
                        $toolCallDelta = $json['choices'][0]['delta']['tool_calls'] ?? null;
                        if ($toolCallDelta) {
                            $hasToolCalls = true;
                            // Collect tool call data for later processing
                            $toolCalls = array_merge($toolCalls, $toolCallDelta);
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

            // If tool calls were detected, process them and get final response
            if ($hasToolCalls) {
                // Reconstruct tool calls from the message for processing
                // Add the accumulated message to messages array
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $fullMessage,
                    'tool_calls' => $toolCalls,
                ];

                // Clear previous chunks and stream tool processing
                foreach (self::processToolCallsWithProgress($toolCalls, $message, $conversationHistory, $messages) as $chunk) {
                    yield $chunk;
                }
            } else {
                // No tool calls, stream complete
                yield self::formatSSE('done', ['message' => $fullMessage]);
            }
        } catch (\Exception $e) {
            Log::error('Streaming error: ' . $e->getMessage());
            yield self::formatSSE('error', ['message' => 'Streaming failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Process tool calls and stream progress indicators, then stream final response
     */
    private static function processToolCallsWithProgress(array $toolCalls, string $userMessage, array $conversationHistory, array $previousMessages): \Generator
    {
        $messages = $previousMessages;
        $toolsUsed = [];

        // Add assistant message with tool calls
        $originalAssistantMessage = end($previousMessages);
        $assistantMessage = [
            'role' => 'assistant',
            'tool_calls' => $toolCalls,
        ];
        if (isset($originalAssistantMessage['content']) && $originalAssistantMessage['content'] !== null) {
            $assistantMessage['content'] = $originalAssistantMessage['content'];
        }

        $messages[] = $assistantMessage;

        // Stream tool progress indicators before executing
        foreach ($toolCalls as $toolCall) {
            $toolName = $toolCall['function']['name'];
            yield self::formatSSE('tool', ['name' => $toolName, 'action' => 'start']);
        }

        // Execute each tool call
        $toolResults = [];
        foreach ($toolCalls as $toolCall) {
            $toolName = $toolCall['function']['name'];
            $parameters = json_decode($toolCall['function']['arguments'], true) ?? [];

            $toolsUsed[] = ['name' => $toolName, 'parameters' => $parameters];

            Log::info("Executing tool: {$toolName}", ['parameters' => $parameters]);

            $result = PluginList::executeTool($toolName, $parameters);

            $toolResults[] = [
                'tool_call_id' => $toolCall['id'],
                'role' => 'tool',
                'name' => $toolName,
                'content' => json_encode($result->toArray()),
            ];

            // Stream completion of this tool
            yield self::formatSSE('tool', ['name' => $toolName, 'action' => 'complete']);
        }

        // Add tool results
        foreach ($toolResults as $toolResult) {
            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => $toolResult['tool_call_id'],
                'content' => $toolResult['content'],
            ];
        }

        // Get final response from AI using streaming
        $requestData = [
            'model' => self::MODEL,
            'messages' => $messages,
            'temperature' => 0.3,
            'max_tokens' => config('ai.max_tokens'),
            'tools' => PluginList::formatToolsForOpenAI(),
            'stream' => true, // Enable streaming for final response
        ];

        $streamResponse = Http::withToken(config('ai.openai.api_key'))
            ->timeout(120)
            ->withOptions(['stream' => true])
            ->post(self::BASE_URL . "/chat/completions", $requestData);

        if (!$streamResponse->successful()) {
            throw new \Exception('OpenAI API error: ' . $streamResponse->status());
        }

        $fullFinalMessage = '';
        $body = $streamResponse->getBody();
        $hasMoreToolCalls = false;

        // Read and parse SSE stream for final response
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

                    // Check for more tool calls
                    $toolCallsData = $json['choices'][0]['delta']['tool_calls'] ?? null;
                    if ($toolCallsData) {
                        $hasMoreToolCalls = true;
                    }

                    $delta = $json['choices'][0]['delta']['content'] ?? '';
                    if ($delta) {
                        $fullFinalMessage .= $delta;
                        // Stream each chunk as it comes from OpenAI
                        yield self::formatSSE('chunk', ['content' => $delta]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Error parsing final response chunk: ' . $e->getMessage());
                    continue;
                }
            }
        }

        // Check if final response includes more tool calls (recursive)
        if ($hasMoreToolCalls) {
            // Reconstruct tool calls from the streamed message would be complex,
            // so fall back to non-streaming for recursive tool execution
            $finalResponse = Http::withToken(config('ai.openai.api_key'))
                ->timeout(120)
                ->post(self::BASE_URL . "/chat/completions", array_merge($requestData, ['stream' => false]))
                ->json();

            $finalAssistantMessage = $finalResponse['choices'][0]['message'];
            if (isset($finalAssistantMessage['tool_calls'])) {
                foreach (self::processToolCallsWithProgress($finalAssistantMessage['tool_calls'], $userMessage, $conversationHistory, $messages) as $chunk) {
                    yield $chunk;
                }
            }
        } else {
            yield self::formatSSE('done', ['message' => $fullFinalMessage]);
        }
    }

    /**
     * Stream text word by word for smooth rendering
     */
    private static function streamText(string $text): \Generator
    {
        if (empty($text)) {
            yield self::formatSSE('done', ['message' => '']);
            return;
        }

        // Stream by word chunks for better performance than char-by-char
        $words = explode(' ', $text);
        $accumulated = '';

        foreach ($words as $index => $word) {
            $accumulated .= $word;
            if ($index < count($words) - 1) {
                $accumulated .= ' ';
            }
            yield self::formatSSE('chunk', ['content' => $word . ($index < count($words) - 1 ? ' ' : '')]);
        }

        yield self::formatSSE('done', ['message' => $text]);
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
