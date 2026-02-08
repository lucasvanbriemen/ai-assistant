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
            // First, check if the response will include tool calls
            $response = self::sendRequest($messages);
            $assistantMessage = $response['choices'][0]['message'];

            // Check if the assistant wants to call tools
            if (isset($assistantMessage['tool_calls'])) {
                // Stream tool progress and get final response
                foreach (self::processToolCallsWithProgress($assistantMessage['tool_calls'], $message, $conversationHistory, $messages) as $chunk) {
                    yield $chunk;
                }
            } else {
                // No tool calls, stream the response normally
                foreach (self::streamText($assistantMessage['content'] ?? '') as $chunk) {
                    yield $chunk;
                }
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

        // Get final response from AI
        $requestData = [
            'model' => self::MODEL,
            'messages' => $messages,
            'temperature' => 0.3,
            'max_tokens' => config('ai.max_tokens'),
            'tools' => PluginList::formatToolsForOpenAI(),
        ];

        $response = Http::withToken(config('ai.openai.api_key'))
            ->timeout(120)
            ->post(self::BASE_URL . "/chat/completions", $requestData)
            ->json();

        $assistantMessage = $response['choices'][0]['message'];

        // Check if the final response also includes tool calls (recursive)
        if (isset($assistantMessage['tool_calls'])) {
            foreach (self::processToolCallsWithProgress($assistantMessage['tool_calls'], $userMessage, $conversationHistory, $messages) as $chunk) {
                yield $chunk;
            }
        } else {
            $finalResponse = $assistantMessage['content'] ?? '';
            // Stream the final response
            foreach (self::streamText($finalResponse) as $chunk) {
                yield $chunk;
            }
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

    /**
     * Send non-streaming request to OpenAI (for checking tool calls and getting responses)
     */
    private static function sendRequest(array $messages): array
    {
        $requestData = [
            'model' => self::MODEL,
            'messages' => $messages,
            'temperature' => 0.3,
            'max_tokens' => config('ai.max_tokens'),
            'tools' => PluginList::formatToolsForOpenAI(),
        ];

        $response = Http::withToken(config('ai.openai.api_key'))
            ->timeout(120)
            ->post(self::BASE_URL . "/chat/completions", $requestData)
            ->json();

        return $response;
    }

    public static function formatSSE(string $event, array $data): string
    {
        return "event: {$event}\ndata: " . json_encode($data) . "\n\n";
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
