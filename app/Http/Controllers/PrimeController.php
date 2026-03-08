<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AIService;

class PrimeController extends Controller
{
    public function chat(Request $request)
    {
        $token = $request->bearerToken();

        if ($token !== env('AGENT_TOKEN')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $messages = $this->formatMessages($request->input('history', []));
        $result = AIService::callSync($messages);

        return response()->json($result);
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
