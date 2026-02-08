<?php

namespace App\AI\Services;

use App\AI\Core\PluginList;
use App\AI\Contracts\ToolResult;
use Illuminate\Support\Facades\Http;

class AIService
{
    private const API_KEY = config('ai.openai.api_key');
    private const MODEL = config('ai.openai.model');
    private const BASE_URL = config('ai.openai.base_url');

    public static function send(string $message, array $conversationHistory = []): array
    {
        $messages = self::buildMessages($message, $conversationHistory);
        $tools = PluginList::getToolsInOpenAIFormat();

        $requestData = [
            'model' => self::MODEL,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => config('ai.max_tokens'),
            'tools' => $tools,
        ];

        $response = Http::withToken(self::API_KEY)
            ->timeout(120)
            ->post(self::BASE_URL."/chat/completions", $requestData)
            ->json();

        // Check if the assistant wants to call tools
        $assistantMessage = $response['choices'][0]['message'];

        if (isset($assistantMessage['tool_calls'])) {
            // Process tool calls (regardless of whether there's also text content)
            return self::processOpenAIToolCalls($assistantMessage['tool_calls'], $message, $conversationHistory, $messages);
        }

        // Regular response (no tool calls)
        $finalResponse = $assistantMessage['content'] ?? '';

        return [
            'success' => true,
            'message' => $finalResponse,
            'history' => array_merge($conversationHistory, [
                ['role' => 'user', 'content' => $message],
                ['role' => 'assistant', 'content' => $finalResponse],
            ]),
            'tools_used' => [],
        ];
    }

    private static function processOpenAIToolCalls(array $toolCalls, string $userMessage, array $conversationHistory, array $previousMessages): array
    {
        $messages = $previousMessages;
        $toolsUsed = [];

        // Add assistant message with tool calls
        // Get the original assistant message which may have content + tool_calls
        $originalAssistantMessage = end($previousMessages);
        $assistantMessage = [
            'role' => 'assistant',
            'tool_calls' => $toolCalls,
        ];
        // Preserve original content if it exists (reasoning/explanation)
        if (isset($originalAssistantMessage['content']) && $originalAssistantMessage['content'] !== null) {
            $assistantMessage['content'] = $originalAssistantMessage['content'];
        }

        $messages[] = $assistantMessage;

        // Execute each tool call
        $toolResults = [];
        foreach ($toolCalls as $toolCall) {
            $toolName = $toolCall['function']['name'];
            $parameters = json_decode($toolCall['function']['arguments'], true) ?? [];

            $toolsUsed[] = ['name' => $toolName, 'parameters' => $parameters];

            $result = PluginList::executeTool($toolName, $parameters);

            $toolResults[] = [
                'tool_call_id' => $toolCall['id'],
                'role' => 'tool',
                'name' => $toolName,
                'content' => json_encode($result->toArray()),
            ];
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
        // Get available tools again in case more tool calls are needed
        $tools = PluginList::getToolsInOpenAIFormat();

        $requestData = [
            'model' => self::MODEL,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => config('ai.max_tokens'),
        ];

        if (!empty($tools)) {
            $requestData['tools'] = $tools;
        }

        $response = Http::withToken(self::API_KEY)
            ->timeout(120)
            ->post(self::BASE_URL."/chat/completions", $requestData)
            ->json();

        if (isset($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'API error',
                'history' => $conversationHistory,
            ];
        }

        $assistantMessage = $response['choices'][0]['message'];

        // Check if the final response also includes tool calls
        if (isset($assistantMessage['tool_calls'])) {
            // Process additional tool calls recursively
            return self::processOpenAIToolCalls($assistantMessage['tool_calls'], $userMessage, $conversationHistory, $messages);
        }

        $finalResponse = $assistantMessage['content'] ?? '';

        return [
            'success' => true,
            'message' => $finalResponse,
            'history' => array_merge($conversationHistory, [
                ['role' => 'user', 'content' => $userMessage],
                ['role' => 'assistant', 'content' => $finalResponse],
            ]),
            'tools_used' => $toolsUsed,
        ];
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

        // Add conversation history (limited by config)
        $maxHistory = config('ai.max_history', 20);
        $historyToInclude = array_slice($conversationHistory, -($maxHistory * 2));

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
