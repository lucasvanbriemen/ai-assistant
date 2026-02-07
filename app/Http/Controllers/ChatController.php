<?php

namespace App\Http\Controllers;

use App\AI\Core\PluginList;
use App\AI\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function sendMessage(Request $request): JsonResponse
    {
        // Increase execution time for complex queries that make multiple API calls
        // Default PHP limit is often 30-60 seconds, which is too short when:
        // - Searching multiple emails
        // - Reading full email content
        // - Extracting information from multiple emails
        set_time_limit(300); // 5 minutes

        $message = $request->input('message');
        $history = $request->input('history', []);
        $response = AIService::send($message, $history);

        return response()->json([
            'success' => true,
            'message' => $response['message'],
            'history' => $response['history']
        ]);
    }
}
