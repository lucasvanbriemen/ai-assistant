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

    public function call(Request $request)
    {
        $token = $request->bearerToken();

        if ($token !== env('AGENT_TOKEN')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $messages = $this->formatMessages($request->input('history', []));
        $result = AIService::callSync($messages);

        return response()->json($result);
    }
}
