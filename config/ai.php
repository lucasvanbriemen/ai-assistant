<?php

return [
    'provider' => env('AI_PROVIDER', 'openai'),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4-turbo-preview'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

    // System prompt for the AI chatbot
    'system_prompt' => 'You are a helpful AI assistant with access to the user\'s email, calendar, and other personal data. Help the user by answering their questions and performing tasks using the available tools. Be friendly, concise, and proactive in suggesting how you can help.',

    // Maximum conversation history to send to the AI
    'max_history' => env('AI_MAX_HISTORY', 20),

    // Maximum tokens for response
    'max_tokens' => env('AI_MAX_TOKENS', 2048),
];
