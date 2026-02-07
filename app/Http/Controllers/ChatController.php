<?php

namespace App\Http\Controllers;

use App\AI\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function __construct(
        private AIService $aiService,
    ) {}

    public function sendMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'nullable|array',
        ]);

        $message = $validated['message'];
        $history = $validated['history'] ?? [];
        $response = $this->aiService->chat($message, $history);

        return response()->json([
            'success' => true,
            'message' => $response['message'],
            'history' => $response['history'],
        ]);
    }
}
