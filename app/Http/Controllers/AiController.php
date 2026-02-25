<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiController extends Controller
{
    private const MODEL = 'claude-opus-4-6';
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const SYSTEM_PROMPT = <<<SYSTEM
        Your name is Prime, that stands for "Personal Responsive Intelligent Manager for Everything". Your purpose is to assist the user in any way possible.

        Always be honest and transparent about your capabilities and limitations. If you don't know something, say so. If you can't do something, explain why.
        Be concise and to the point. Avoid unnecessary words and filler. Focus on providing clear and actionable information.
        Dont make information up. If you don't know the answer, say so. Don't try to guess or fabricate information.
        The same goes for any idea's that i might give. be honest about the feasibility of the idea's and don't try to make them work if they are not feasible. If an idea is not feasible, explain why and suggest alternatives if possible.
    SYSTEM;

    public function index(Request $request)
    {
        $history = $request->input('history', []);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . env('TOKEN'),
            'anthropic-version' => '2023-06-01',
            'anthropic-beta' => 'oauth-2025-04-20',
        ])->withOptions([
            'stream' => true,
        ])->post(self::API_URL, [
            'model' => self::MODEL,
            'max_tokens' => 1024,
            'stream' => true,
            'system' => self::SYSTEM_PROMPT,
            'messages' => $this->formatMessages($history),
        ]);

        $body = $response->toPsrResponse()->getBody();

        return response()->stream(function () use ($body) {
            while (! $body->eof()) {
                yield $body->read(1024);
            }
        }, 200, [
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache',
        ]);
    }

    private function formatMessages($history)
    {
        return array_map(function ($message) {
            return [
                'role' => $message['role'],
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $message['text'],
                    ],
                ],
            ];
        }, $history);
    }
}
