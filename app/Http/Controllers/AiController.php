<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiController extends Controller
{
    private const MODEL = 'claude-opus-4-6';
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    public function index(Request $request)
    {
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
            'messages' => [
                ['role' => 'user', 'content' => $request->input('prompt')],
            ],
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
}
