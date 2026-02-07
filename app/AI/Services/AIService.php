<?php

namespace App\AI\Services;

use App\AI\Core\PluginList;
use App\AI\Contracts\ToolResult;
use Illuminate\Support\Facades\Http;

class AIService
{
    private PluginList $registry;
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct(PluginList $registry)
    {
        $this->registry = $registry;
        $this->apiKey = config('ai.openai.api_key');
        $this->model = config('ai.openai.model');
        $this->baseUrl = config('ai.openai.base_url');
    }

    /**
     * Send a message and get a response, optionally executing tools
     */
    public function chat(string $message, array $conversationHistory = []): array
    {
        // Build messages for the API
        $messages = $this->buildMessages($message, $conversationHistory);

        // Get available tools
        $tools = $this->registry->getToolsInOpenAIFormat();

        $requestData = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => config('ai.max_tokens'),
        ];

        if (!empty($tools)) {
            $requestData['tools'] = $tools;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(60)
                ->post("{$this->baseUrl}/chat/completions", $requestData)
                ->json();

            if (isset($response['error'])) {
                return [
                    'success' => false,
                    'error' => $response['error']['message'] ?? 'API error',
                    'history' => $conversationHistory,
                ];
            }

            // Check if the assistant wants to call tools
            $assistantMessage = $response['choices'][0]['message'];

            if (isset($assistantMessage['tool_calls'])) {
                // Process tool calls (regardless of whether there's also text content)
                return $this->processOpenAIToolCalls($assistantMessage['tool_calls'], $message, $conversationHistory, $messages);
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
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to communicate with OpenAI: ' . $e->getMessage(),
                'history' => $conversationHistory,
            ];
        }
    }

    private function processOpenAIToolCalls(array $toolCalls, string $userMessage, array $conversationHistory, array $previousMessages): array
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

            $result = $this->registry->executeTool($toolName, $parameters);

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
        $tools = $this->registry->getToolsInOpenAIFormat();

        try {
            $requestData = [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => config('ai.max_tokens'),
            ];

            if (!empty($tools)) {
                $requestData['tools'] = $tools;
            }

            $response = Http::withToken($this->apiKey)
                ->timeout(60)
                ->post("{$this->baseUrl}/chat/completions", $requestData)
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
                return $this->processOpenAIToolCalls($assistantMessage['tool_calls'], $userMessage, $conversationHistory, $messages);
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
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get final response: ' . $e->getMessage(),
                'history' => $conversationHistory,
            ];
        }
    }
    private function buildMessages(string $message, array $conversationHistory): array
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
