<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/tes2t', function (Request $request) {
    return response()->stream(function (): Generator {
        foreach (['developer', 'admin'] as $string) {
            sleep(2); // Simulate delay between chunks...
            yield $string . "\n";
        }
    }, 200, ['X-Accel-Buffering' => 'no']);
});

Route::get('/test', function () {
    return response()->stream(function (): Generator {

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . env('TOKEN'),
            'anthropic-version' => '2023-06-01',
            'anthropic-beta' => 'oauth-2025-04-20',
        ])->withOptions([
            'stream' => true,
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-opus-4-6',
            'max_tokens' => 1024,
            'stream' => true,
            'messages' => [
                ['role' => 'user', 'content' => 'Explain the theory of relativity in 1 paragraph.'],
            ],
        ]);

        $body = $response->toPsrResponse()->getBody();

        while (! $body->eof()) {
            yield $body->read(1024);
        }
    }, 200, [
        'X-Accel-Buffering' => 'no',
        'Cache-Control' => 'no-cache',
    ]);
});
