<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AIService;

class AiController extends Controller
{
    public function index(Request $request)
    {
        $messages = $this->formatMessages($request->input('history', []));
        $response = AIService::call($messages);

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
