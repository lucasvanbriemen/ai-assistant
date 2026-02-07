<?php

namespace App\Http\Controllers;

use App\AI\Core\PluginRegistry;
use App\AI\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function __construct(
        private AIService $aiService,
        private PluginRegistry $registry,
    ) {}

    /**
     * Get all available tools
     */
    public function getTools(): JsonResponse
    {
        return response()->json([
            'tools' => $this->registry->getAllTools(),
            'plugins' => array_map(function ($plugin) {
                return [
                    'name' => $plugin->getName(),
                    'description' => $plugin->getDescription(),
                ];
            }, $this->registry->getPlugins()),
        ]);
    }

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

        // Call AI service
        $response = $this->aiService->chat($message, $history);

        if (!$response['success']) {
            return response()->json([
                'success' => false,
                'error' => $response['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $response['message'],
            'history' => $response['history'],
            'tools_used' => $response['tools_used'] ?? [],
        ]);
    }
}
