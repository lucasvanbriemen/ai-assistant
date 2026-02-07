<?php

namespace App\Http\Controllers;

use App\AI\Core\PluginList;
use App\AI\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    /**
     * Send a message and get a response
     */
    public function sendMessage(Request $request): JsonResponse
    {
        // Increase execution time for complex queries that make multiple API calls
        // Default PHP limit is often 30-60 seconds, which is too short when:
        // - Searching multiple emails
        // - Reading full email content
        // - Extracting information from multiple emails
        set_time_limit(300); // 5 minutes

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'nullable|array',
        ]);

        $message = $validated['message'];
        $history = $validated['history'] ?? [];
        $response = AIService::send($message, $history);

        return response()->json([
            'success' => true,
            'message' => $response['message'],
            'history' => $response['history'],
            'tools_used' => $response['tools_used'] ?? [],
        ]);
    }
}
