<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/tes2t', function (Request $request) {
    return response()->stream(function (): Generator {
        foreach (['developer', 'admin'] as $string) {
            sleep(2); // Simulate delay between chunks...
            yield $string . "\n";
        }
    }, 200, ['X-Accel-Buffering' => 'no']);
});

Route::get('/test', function (Request $request) {
    return response()->stream(function () {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.anthropic.com/v1/messages');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'claude-opus-4-6',
            'max_tokens' => 1024,
            'stream' => true,
            'messages' => [
                ['role' => 'user', 'content' => 'Write a haiku.'],
            ],
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . env('TOKEN'),
            'anthropic-version: 2023-06-01',
            'anthropic-beta: oauth-2025-04-20',
        ]);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) {
            echo $data;
            ob_flush();
            flush();
            return strlen($data);
        });

        curl_exec($ch);
        curl_close($ch);
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'X-Accel-Buffering' => 'no',
        'Cache-Control' => 'no-cache',
    ]);
});
