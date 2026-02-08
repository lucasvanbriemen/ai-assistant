<?php

namespace App\AI\Services;

use App\AI\Core\PluginList;
use Illuminate\Support\Facades\Http;

class AIService
{
    private const BASE_URL = 'https://api.openai.com/v1';
    private const MODEL = 'gpt-4o-mini';
    private const TEMPERATURE = 0.3;

    public static function send(string $message, array $conversationHistory): \Generator
    {
        $messages = self::buildMessages($message, $conversationHistory);
        $requestData = self::buildRequestData($messages);

        $response = self::makeRequest(self::BASE_URL . "/chat/completions", $requestData)
            ->json();

        $assistantMessage = $response['choices'][0]['message'];

        // Check if the assistant wants to call tools
        if (isset($assistantMessage['tool_calls'])) {
            // Process tools and get final response, then stream it
            foreach (self::processToolCallsWithProgress($assistantMessage['tool_calls'], $message, $conversationHistory, $messages) as $chunk) {
                yield $chunk;
            }
        } else {
            // Get streaming response for text-only queries
            foreach (self::streamResponse(array_merge($requestData, ['stream' => true]), 60) as $chunk) {
                yield $chunk;
            }
        }
    }

    /**
     * Process tool calls and stream progress indicators, then stream final response
     */
    private static function processToolCallsWithProgress(array $toolCalls, string $userMessage, array $conversationHistory, array $previousMessages): \Generator
    {
        $messages = $previousMessages;

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
        $requestData = self::buildRequestData($messages, true);

        $streamedData = self::parseStreamResponse($requestData, 120);
        $fullFinalMessage = $streamedData['message'];
        $hasMoreToolCalls = $streamedData['hasMoreToolCalls'];

        // Stream each chunk as it comes from OpenAI
        foreach ($streamedData['chunks'] as $chunk) {
            yield self::formatSSE('chunk', ['content' => $chunk]);
        }

        // Check if final response includes more tool calls (recursive)
        if ($hasMoreToolCalls) {
            // Reconstruct tool calls from the streamed message would be complex,
            // so fall back to non-streaming for recursive tool execution
            $finalResponse = self::makeRequest(self::BASE_URL . "/chat/completions", array_merge($requestData, ['stream' => false]))
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
     * Make an authenticated HTTP request to OpenAI API
     */
    private static function makeRequest(string $url, array $data)
    {
        return Http::withToken(config('ai.openai.api_key'))
            ->timeout(120)
            ->post($url, $data);
    }

    /**
     * Make a streaming HTTP request and parse SSE response
     */
    private static function streamResponse(array $requestData, int $timeout = 60): \Generator
    {
        $streamedData = self::parseStreamResponse($requestData, $timeout);

        // Stream each chunk
        foreach ($streamedData['chunks'] as $chunk) {
            yield self::formatSSE('chunk', ['content' => $chunk]);
        }

        yield self::formatSSE('done', ['message' => $streamedData['message']]);
    }

    /**
     * Parse a streaming SSE response and collect data
     */
    private static function parseStreamResponse(array $requestData, int $timeout = 60): array
    {
        $streamResponse = Http::withToken(config('ai.openai.api_key'))
            ->timeout($timeout)
            ->withOptions(['stream' => true])
            ->post(self::BASE_URL . "/chat/completions", $requestData);

        if (!$streamResponse->successful()) {
            throw new \Exception('OpenAI API error: ' . $streamResponse->status());
        }

        $body = $streamResponse->getBody();
        $fullMessage = '';
        $chunks = [];
        $hasMoreToolCalls = false;

        // Read and parse SSE stream
        while (!$body->eof()) {
            $line = self::readLine($body);
            if (empty($line) || $line === 'data: [DONE]') {
                continue;
            }

            if (str_starts_with($line, 'data: ')) {
                $json = json_decode(substr($line, 6), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    continue;
                }

                // Check for more tool calls
                if (isset($json['choices'][0]['delta']['tool_calls'])) {
                    $hasMoreToolCalls = true;
                }

                $delta = $json['choices'][0]['delta']['content'] ?? '';
                if ($delta) {
                    $fullMessage .= $delta;
                    $chunks[] = $delta;
                }
            }
        }

        return [
            'message' => $fullMessage,
            'chunks' => $chunks,
            'hasMoreToolCalls' => $hasMoreToolCalls,
        ];
    }

    /**
     * Build request data for OpenAI API calls
     */
    private static function buildRequestData(array $messages, bool $stream = false): array
    {
        $data = [
            'model' => self::MODEL,
            'messages' => $messages,
            'temperature' => self::TEMPERATURE,
            'max_tokens' => config('ai.max_tokens'),
            'tools' => PluginList::formatToolsForOpenAI(),
        ];

        if ($stream) {
            $data['stream'] = true;
        }

        return $data;
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
