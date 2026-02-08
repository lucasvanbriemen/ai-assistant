<?php

namespace App\Http\Controllers;

use App\AI\Core\PluginList;
use App\AI\Services\AIService;
use App\AI\Services\AIStreamService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function streamMessage(Request $request): StreamedResponse
    {
        set_time_limit(180); // 3 minutes for streaming

        $message = $request->input('message');
        $history = $request->input('history', []);

        return response()->stream(function () use ($message, $history) {
            try {
                foreach (AIStreamService::streamResponse($message, $history) as $chunk) {
                    echo $chunk;
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Streaming error: ' . $e->getMessage());
                echo AIStreamService::formatSSE('error', ['message' => 'Streaming failed']);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
