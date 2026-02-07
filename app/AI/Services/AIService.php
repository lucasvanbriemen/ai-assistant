<?php

namespace App\AI\Services;

use Illuminate\Support\Facades\Http;

class AIService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->provider = config('ai.provider', 'openai');
        $this->apiKey = config('ai.openai.api_key');
        $this->model = config('ai.openai.model');
        $this->baseUrl = config('ai.openai.base_url');
    }

    public function chat(string $message, array $conversationHistory = []): array
    {
        $messages = $this->buildMessages($message, $conversationHistory);

        $requestData = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => config('ai.max_tokens'),
        ];

        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/chat/completions", $requestData)
            ->json();

        $assistantMessage = $response['choices'][0]['message']['content'] ?? '';

        return [
            'success' => true,
            'message' => $assistantMessage,
            'history' => array_merge($conversationHistory, [
                ['role' => 'user', 'content' => $message],
                ['role' => 'assistant', 'content' => $assistantMessage],
            ])
        ];
    }

    private function buildMessages(string $message, array $conversationHistory): array
    {
        $messages = [];

        $systemPrompt = config('ai.system_prompt');
        if ($systemPrompt) {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }

        $historyToInclude = array_slice($conversationHistory, -(config('ai.max_history') * 2));
        foreach ($historyToInclude as $entry) {
            if (isset($entry['role']) && isset($entry['content'])) {
                $messages[] = $entry;
            }
        }

        $messages[] = [
            'role' => 'user',
            'content' => $message,
        ];

        return $messages;
    }
}
