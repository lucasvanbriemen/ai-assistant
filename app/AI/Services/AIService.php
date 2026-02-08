<?php

namespace App\AI\Services;

use App\AI\Core\PluginList;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class AIService
{
    private const BASE_URL = 'https://api.openai.com/v1';
    private const MODEL = 'gpt-4o-mini';
    private const TEMPERATURE = 0.3;

    public static function send(string $message, array $conversationHistory): \Generator
    {
        $messages = self::buildMessages($message, $conversationHistory);
        $requestData = self::buildRequestData($messages);

        $response = self::makeRequest(self::BASE_URL . "/chat/completions", $requestData)->json();
        $assistantMessage = $response['choices'][0]['message'] ?? null;

        if (isset($assistantMessage['tool_calls'])) {
            yield from self::processToolCalls($assistantMessage['tool_calls'], $message, $conversationHistory, $messages);
        } else {
            yield from self::streamResponse(array_merge($requestData, ['stream' => true]));
        }
    }

    private static function processToolCalls(array $toolCalls, string $userMessage, array $conversationHistory, array $previousMessages): \Generator
    {
        $messages = $previousMessages;
        $assistantMessage = self::buildAssistantMessage($toolCalls, $previousMessages);
        $messages[] = $assistantMessage;

        // Stream tool start indicators
        foreach ($toolCalls as $toolCall) {
            $toolName = $toolCall['function']['name'] ?? '';
            if ($toolName) {
                yield self::formatSSE('tool', ['name' => $toolName, 'action' => 'start']);
            }
        }

        // Execute tools and collect results
        $toolResults = self::executeToolCalls($toolCalls);
        foreach ($toolResults as $result) {
            yield self::formatSSE('tool', ['name' => $result['name'], 'action' => 'complete']);
            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => $result['tool_call_id'],
                'content' => $result['content'],
            ];
        }

        // Get final response from AI
        $requestData = self::buildRequestData($messages, true);
        $streamedData = self::parseStreamResponse($requestData);

        // Stream response chunks
        foreach ($streamedData['chunks'] as $chunk) {
            yield self::formatSSE('chunk', ['content' => $chunk]);
        }

        // Handle recursive tool calls
        if ($streamedData['hasMoreToolCalls']) {
            $finalResponse = self::makeRequest(self::BASE_URL . "/chat/completions", array_merge($requestData, ['stream' => false]))->json();
            $finalAssistantMessage = $finalResponse['choices'][0]['message'] ?? null;

            if ($finalAssistantMessage && isset($finalAssistantMessage['tool_calls'])) {
                yield from self::processToolCalls($finalAssistantMessage['tool_calls'], $userMessage, $conversationHistory, $messages);
                return;
            }
        }

        yield self::formatSSE('done', ['message' => $streamedData['message']]);
    }

    private static function makeRequest(string $url, array $data): Response
    {
        return Http::withToken(config('ai.openai.api_key'))
            ->post($url, $data);
    }

    private static function streamResponse(array $requestData): \Generator
    {
        $streamedData = self::parseStreamResponse($requestData);

        foreach ($streamedData['chunks'] as $chunk) {
            yield self::formatSSE('chunk', ['content' => $chunk]);
        }

        yield self::formatSSE('done', ['message' => $streamedData['message']]);
    }

    private static function parseStreamResponse(array $requestData): array
    {
        $streamResponse = Http::withToken(config('ai.openai.api_key'))
            ->withOptions(['stream' => true])
            ->post(self::BASE_URL . "/chat/completions", $requestData);

        $body = $streamResponse->getBody();
        $fullMessage = '';
        $chunks = [];
        $hasMoreToolCalls = false;

        while (!$body->eof()) {
            $line = self::readLine($body);
            if (empty($line) || $line === 'data: [DONE]') {
                continue;
            }

            if (!str_starts_with($line, 'data: ')) {
                continue;
            }

            $json = json_decode(substr($line, 6), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }

            $delta = $json['choices'][0]['delta'] ?? [];

            if (isset($delta['tool_calls'])) {
                $hasMoreToolCalls = true;
            }

            $content = $delta['content'] ?? '';
            if ($content) {
                $fullMessage .= $content;
                $chunks[] = $content;
            }
        }

        return [
            'message' => $fullMessage,
            'chunks' => $chunks,
            'hasMoreToolCalls' => $hasMoreToolCalls,
        ];
    }

    private static function buildRequestData(array $messages, bool $stream = false): array
    {
        return [
            'model' => self::MODEL,
            'messages' => $messages,
            'temperature' => self::TEMPERATURE,
            'max_tokens' => config('ai.max_tokens'),
            'tools' => PluginList::formatToolsForOpenAI(),
            'stream' => $stream,
        ];
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
        $messages = [
            [
                'role' => 'system',
                'content' => self::buildSystemPrompt(),
            ],
        ];

        $historyToInclude = array_slice($conversationHistory, -(config('ai.max_history') * 2));
        foreach ($historyToInclude as $entry) {
            if (isset($entry['role'], $entry['content'])) {
                $messages[] = $entry;
            }
        }

        $messages[] = [
            'role' => 'user',
            'content' => $message,
        ];

        return $messages;
    }

    private static function buildSystemPrompt(): string
    {
        $basePrompt = config('ai.system_prompt', 'You are a helpful AI assistant.');
        $today = date('l, F j, Y');

        return "{$basePrompt}\n\nCurrent date and time: {$today}. Use this to interpret relative dates like 'last Friday', 'tomorrow', 'next week', etc.";
    }

    private static function buildAssistantMessage(array $toolCalls, array $previousMessages): array
    {
        $originalMessage = end($previousMessages);
        $message = [
            'role' => 'assistant',
            'tool_calls' => $toolCalls,
        ];

        if (isset($originalMessage['content']) && $originalMessage['content'] !== null) {
            $message['content'] = $originalMessage['content'];
        }

        return $message;
    }

    private static function executeToolCalls(array $toolCalls): array
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            $toolName = $toolCall['function']['name'] ?? '';
            $arguments = $toolCall['function']['arguments'] ?? '{}';

            if (!$toolName) {
                continue;
            }

            $parameters = json_decode($arguments, true) ?? [];
            $result = PluginList::executeTool($toolName, $parameters);

            $results[] = [
                'tool_call_id' => $toolCall['id'],
                'name' => $toolName,
                'content' => json_encode($result->toArray()),
            ];
        }

        return $results;
    }
}
