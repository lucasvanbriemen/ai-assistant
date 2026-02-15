<?php

namespace App\AI\Services;

use App\AI\Core\PluginList;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

class AIService
{
    private const BASE_URL = 'https://api.openai.com/v1';
    private const MODEL = 'gpt-4o-mini';
    private const TEMPERATURE = 0.3;

    private static ?string $cachedSystemPrompt = null;
    private static ?string $cachedPromptDate = null;
    private static $httpClient = null;

    /**
     * Get a persistent HTTP client with connection pooling
     */
    private static function getHttpClient()
    {
        if (self::$httpClient === null) {
            self::$httpClient = Http::withToken(config('ai.openai.api_key'))
                ->withOptions([
                    'connect_timeout' => 5,
                    'timeout' => 120,
                    'http_errors' => false,
                    // Enable HTTP keep-alive and connection pooling
                    'curl' => [
                        CURLOPT_TCP_KEEPALIVE => 1,
                        CURLOPT_TCP_KEEPIDLE => 120,
                        CURLOPT_TCP_KEEPINTVL => 60,
                    ],
                ]);
        }
        return self::$httpClient;
    }

    public static function send(string $message, array $conversationHistory): \Generator
    {
        $startTime = microtime(true);
        $messages = self::buildMessages($message, $conversationHistory);
        $requestData = self::buildRequestData($messages, true); // Enable streaming from the start

        // Send initial "thinking" event
        yield self::formatSSE('thinking', ['status' => 'start']);

        // Stream immediately and detect tool calls FROM the stream
        yield from self::streamWithToolDetection($requestData, $message, $conversationHistory, $messages, $startTime);
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

        // Send thinking event before getting final response
        yield self::formatSSE('thinking', ['status' => 'start']);

        // Get final response from AI with real-time streaming
        $requestData = self::buildRequestData($messages, true);

        // End thinking and start streaming response
        yield self::formatSSE('thinking', ['status' => 'end']);

        // Stream response in real-time
        $streamResponse = self::getHttpClient()
            ->withOptions(['stream' => true])
            ->post(self::BASE_URL . "/chat/completions", $requestData);

        $body = $streamResponse->getBody();
        $fullMessage = '';
        $hasMoreToolCalls = false;

        // Pre-compile constants for loop
        $dataPrefix = 'data: ';
        $dataPrefixLen = 6;

        while (!$body->eof()) {
            $line = self::readLine($body);

            // Fast path: skip empty lines and [DONE]
            if ($line === '' || $line === 'data: [DONE]') {
                continue;
            }

            // Fast path: check prefix
            if (strncmp($line, $dataPrefix, $dataPrefixLen) !== 0) {
                continue;
            }

            $json = json_decode(substr($line, $dataPrefixLen), true);
            if ($json === null || !isset($json['choices'][0]['delta'])) {
                continue;
            }

            $delta = $json['choices'][0]['delta'];

            if (isset($delta['tool_calls'])) {
                $hasMoreToolCalls = true;
            }

            if (isset($delta['content'])) {
                $fullMessage .= $delta['content'];
                yield self::formatSSE('chunk', ['content' => $delta['content']]);

                if (ob_get_level() > 0) {
                    ob_flush();
                    flush();
                }
            }
        }

        // Handle recursive tool calls
        if ($hasMoreToolCalls) {
            $finalResponse = self::makeRequest(self::BASE_URL . "/chat/completions", array_merge($requestData, ['stream' => false]))->json();
            $finalAssistantMessage = $finalResponse['choices'][0]['message'] ?? null;

            if ($finalAssistantMessage && isset($finalAssistantMessage['tool_calls'])) {
                yield from self::processToolCalls($finalAssistantMessage['tool_calls'], $userMessage, $conversationHistory, $messages);
                return;
            }
        }

        yield self::formatSSE('done', ['message' => $fullMessage]);
    }

    private static function makeRequest(string $url, array $data): Response
    {
        return self::getHttpClient()->post($url, $data);
    }

    private static function streamResponse(array $requestData): \Generator
    {
        $streamedData = self::parseStreamResponse($requestData);

        foreach ($streamedData['chunks'] as $chunk) {
            yield self::formatSSE('chunk', ['content' => $chunk]);
        }

        yield self::formatSSE('done', ['message' => $streamedData['message']]);
    }

    private static function streamWithToolDetection(array $requestData, string $userMessage, array $conversationHistory, array $messages, float $startTime): \Generator
    {
        $streamResponse = self::getHttpClient()
            ->withOptions(['stream' => true])
            ->post(self::BASE_URL . "/chat/completions", $requestData);

        $body = $streamResponse->getBody();
        $fullMessage = '';
        $accumulatedToolData = [];

        // End thinking immediately as we connect to stream
        yield self::formatSSE('thinking', ['status' => 'end']);

        // Pre-compile constants used in loop
        $dataPrefix = 'data: ';
        $dataPrefixLen = 6;
        $dataDone = 'data: [DONE]';

        while (!$body->eof()) {
            $line = self::readLine($body);

            // Fast path: skip empty lines and [DONE] marker
            if ($line === '' || $line === $dataDone) {
                continue;
            }

            // Fast path: check prefix without function call
            if (strncmp($line, $dataPrefix, $dataPrefixLen) !== 0) {
                continue;
            }

            // Extract and decode JSON (minimize substr calls)
            $jsonData = substr($line, $dataPrefixLen);
            $json = json_decode($jsonData, true);

            if ($json === null) {
                continue;
            }

            // Direct array access (faster than null coalescing for hot path)
            if (!isset($json['choices'][0]['delta'])) {
                continue;
            }

            $delta = $json['choices'][0]['delta'];

            // Detect and accumulate tool calls from stream
            if (isset($delta['tool_calls'])) {
                foreach ($delta['tool_calls'] as $toolCallDelta) {
                    $index = $toolCallDelta['index'] ?? 0;

                    if (!isset($accumulatedToolData[$index])) {
                        $accumulatedToolData[$index] = [
                            'id' => '',
                            'type' => 'function',
                            'function' => ['name' => '', 'arguments' => '']
                        ];
                    }

                    if (isset($toolCallDelta['id'])) {
                        $accumulatedToolData[$index]['id'] = $toolCallDelta['id'];
                    }

                    if (isset($toolCallDelta['function']['name'])) {
                        $accumulatedToolData[$index]['function']['name'] .= $toolCallDelta['function']['name'];
                    }

                    if (isset($toolCallDelta['function']['arguments'])) {
                        $accumulatedToolData[$index]['function']['arguments'] .= $toolCallDelta['function']['arguments'];
                    }
                }
                continue;
            }

            // Hot path: stream text content
            if (isset($delta['content'])) {
                $content = $delta['content'];

                $fullMessage .= $content;

                // Batch output buffering (flush every chunk, not every operation)
                yield self::formatSSE('chunk', ['content' => $content]);

                // Single flush call
                if (ob_get_level() > 0) {
                    ob_flush();
                    flush();
                }
            }
        }

        $streamEnd = microtime(true);

        // If tool calls were detected, process them recursively
        if (!empty($accumulatedToolData)) {
            $toolCalls = array_values($accumulatedToolData);
            yield from self::processToolCalls($toolCalls, $userMessage, $conversationHistory, $messages);
            return;
        }

        // Otherwise, we're done
        yield self::formatSSE('done', ['message' => $fullMessage]);
    }

    private static function streamResponseRealtime(array $requestData): \Generator
    {
        $streamResponse = self::getHttpClient()
            ->withOptions(['stream' => true])
            ->post(self::BASE_URL . "/chat/completions", $requestData);

        $body = $streamResponse->getBody();
        $fullMessage = '';

        // Pre-compile constants
        $dataPrefix = 'data: ';
        $dataPrefixLen = 6;

        while (!$body->eof()) {
            $line = self::readLine($body);

            if ($line === '' || $line === 'data: [DONE]') {
                continue;
            }

            if (strncmp($line, $dataPrefix, $dataPrefixLen) !== 0) {
                continue;
            }

            $json = json_decode(substr($line, $dataPrefixLen), true);
            if ($json === null || !isset($json['choices'][0]['delta'])) {
                continue;
            }

            $delta = $json['choices'][0]['delta'];

            if (isset($delta['content'])) {
                $content = $delta['content'];

                $fullMessage .= $content;
                yield self::formatSSE('chunk', ['content' => $content]);

                if (ob_get_level() > 0) {
                    ob_flush();
                    flush();
                }
            }
        }

        $streamEnd = microtime(true);

        yield self::formatSSE('done', ['message' => $fullMessage]);
    }

    private static function parseStreamResponse(array $requestData): array
    {
        $streamResponse = self::getHttpClient()
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

    public static function processAutonomously(string $content): array
    {
        $todayYMD = date('Y-m-d');
        $today = date('l, F j, Y');

        $systemPrompt = "You are a background processing agent. Today is {$today} ({$todayYMD}).\n\n"
            . "Analyze the following content and take appropriate actions using your available tools.\n"
            . "You MUST use tools to store information — do NOT just summarize.\n\n"
            . "Guidelines:\n"
            . "- Store people/contacts using store_person\n"
            . "- Store important facts, notes, and summaries using store_note\n"
            . "- Create calendar events for dates/appointments using create_calendar_event\n"
            . "- Create relationships between entities using create_relationship\n"
            . "- You may call multiple tools in sequence\n"
            . "- After taking all actions, respond with a brief summary of what you did";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $content],
        ];

        $tools = PluginList::formatToolsForOpenAI();
        $toolsUsed = [];
        $maxIterations = 10;

        for ($i = 0; $i < $maxIterations; $i++) {
            $response = self::makeRequest(self::BASE_URL . '/chat/completions', [
                'model' => self::MODEL,
                'messages' => $messages,
                'temperature' => self::TEMPERATURE,
                'max_tokens' => config('ai.max_tokens'),
                'tools' => $tools,
            ])->json();

            $choice = $response['choices'][0]['message'] ?? null;

            if (!$choice) {
                break;
            }

            if (empty($choice['tool_calls'])) {
                Log::info('AIService::processAutonomously completed', [
                    'tools_used' => $toolsUsed,
                    'message' => $choice['content'] ?? '',
                ]);

                return [
                    'message' => $choice['content'] ?? '',
                    'tools_used' => $toolsUsed,
                ];
            }

            $messages[] = $choice;

            $toolResults = self::executeToolCalls($choice['tool_calls']);
            foreach ($toolResults as $result) {
                $toolsUsed[] = $result['name'];
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $result['tool_call_id'],
                    'content' => $result['content'],
                ];
            }
        }

        Log::info('AIService::processAutonomously completed', [
            'tools_used' => $toolsUsed,
            'iterations' => $maxIterations,
        ]);

        return [
            'message' => 'Max iterations reached',
            'tools_used' => $toolsUsed,
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
        // Use stream_get_line for better performance (reads until delimiter)
        $line = '';
        while (!$stream->eof()) {
            $char = $stream->read(1);
            if ($char === "\n") {
                break;
            }
            $line .= $char;

            // Safety check for very long lines
            if (strlen($line) > 100000) {
                break;
            }
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
        $todayYMD = date('Y-m-d');

        // Cache system prompt for the day (only rebuild when date changes)
        if (self::$cachedSystemPrompt !== null && self::$cachedPromptDate === $todayYMD) {
            return self::$cachedSystemPrompt;
        }

        $basePrompt = config('ai.system_prompt', 'You are a helpful AI assistant.');
        $today = date('l, F j, Y'); // e.g., "Tuesday, February 11, 2026"
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        $prompt = "{$basePrompt}\n\n=== CURRENT DATE AND TIME ===\nToday is: {$today}\nDate in YYYY-MM-DD format: {$todayYMD}\nTomorrow in YYYY-MM-DD format: {$tomorrow}\n\n=== DATE COMPARISON RULES ===\nWhen answering time-sensitive queries:\n1. Extract the EXACT date from email content (do NOT modify it)\n2. Compare event date to current date ({$todayYMD})\n3. ONLY return events where event date matches the requested timeframe\n4. NEVER change dates to match user queries\n\nEXAMPLE (assuming today is {$todayYMD}):\n- User asks: \"What movie am I going tonight?\"\n- Email says: \"The Shining on 2026-02-09 at 18:00\"\n- Correct response: \"I don't see any movies tonight. You had The Shining on February 9, which was 2 days ago.\"\n- WRONG response: \"Tonight you're seeing The Shining on February 11\" (this changes the date!)";

        // Cache it
        self::$cachedSystemPrompt = $prompt;
        self::$cachedPromptDate = $todayYMD;

        return $prompt;
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
