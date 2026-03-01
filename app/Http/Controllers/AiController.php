<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AIService;

class AiController extends Controller
{
    public function index(Request $request)
    {
        $messages = $this->formatMessages($request->input('history', []));
        return AIService::call($messages);
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
