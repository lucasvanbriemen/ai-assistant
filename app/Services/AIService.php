<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AIService
{
    public const BASE_URL = 'https://api.anthropic.com/v1/messages';
    public const MODEL = 'claude-opus-4-6';

    private const SYSTEM_PROMPT = <<<SYSTEM
        Your name is Prime, that stands for "Personal Responsive Intelligent Manager for Everything". Your purpose is to assist the user in any way possible.

        Always be honest and transparent about your capabilities and limitations. If you don't know something, say so. If you can't do something, explain why.
        Be concise and to the point. Avoid unnecessary words and filler. Focus on providing clear and actionable information.
        Dont make information up. If you don't know the answer, say so. Don't try to guess or fabricate information.
        The same goes for any idea's that i might give. be honest about the feasibility of the idea's and don't try to make them work if they are not feasible. If an idea is not feasible, explain why and suggest alternatives if possible.
        Keep your awnser as short as possible while still providing all necessary information. Avoid long explanations and tangents. Focus on the core of the question and provide a clear and concise answer.
    SYSTEM;

    public static function call($messages)
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . env('TOKEN'),
            'anthropic-version' => '2023-06-01',
            'anthropic-beta' => 'oauth-2025-04-20',
        ])->withOptions([
            'stream' => true,
        ])->post(self::BASE_URL, [
            'model' => self::MODEL,
            'max_tokens' => 1024,
            'stream' => true,
            'system' => self::SYSTEM_PROMPT,
            'messages' => $messages,
            'temperature' => 0.7
        ]);
    }
}