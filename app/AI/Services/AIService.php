<?php

namespace App\AI\Services;

use Illuminate\Support\Facades\Http;

class AIService
{
    private string $provider;
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->provider = config('ai.provider', 'openai');

        if ($this->provider === 'openai') {
            $this->apiKey = config('ai.openai.api_key');
            $this->model = config('ai.openai.model');
            $this->baseUrl = config('ai.openai.base_url');
        }
    }

    /**
     * Send a message and get a response, optionally executing tools
     */
    public function chat(string $message, array $conversationHistory = []): array
    {
        if ($this->provider === 'openai') {
            return $this->chatWithOpenAI($message, $conversationHistory);
        }
    }

    private function chatWithOpenAI(string $message, array $conversationHistory): array
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

            if ($assistantMessage['content'] === null && isset($assistantMessage['tool_calls'])) {
                // Process tool calls
                return $this->processOpenAIToolCalls($assistantMessage['tool_calls'], $message, $conversationHistory, $messages);
            }

            // Regular response
            $finalResponse = $assistantMessage['content'] ?? '';

            return [
                'success' => true,
                'message' => $finalResponse,
                'history' => array_merge($conversationHistory, [
                    ['role' => 'user', 'content' => $message],
                    ['role' => 'assistant', 'content' => $finalResponse],
                ])
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
        $messages[] = [
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => $toolCalls,
        ];

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
        try {
            $response = Http::withToken($this->apiKey)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => 0.7,
                    'max_tokens' => config('ai.max_tokens'),
                ])
                ->json();

            if (isset($response['error'])) {
                return [
                    'success' => false,
                    'error' => $response['error']['message'] ?? 'API error',
                    'history' => $conversationHistory,
                ];
            }

            $finalResponse = $response['choices'][0]['message']['content'] ?? '';

            return [
                'success' => true,
                'message' => $finalResponse,
                'history' => array_merge($conversationHistory, [
                    ['role' => 'user', 'content' => $userMessage],
                    ['role' => 'assistant', 'content' => $finalResponse],
                ])
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
        // Start with system prompt for Anthropic, or include in messages for OpenAI
        $messages = [];

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
