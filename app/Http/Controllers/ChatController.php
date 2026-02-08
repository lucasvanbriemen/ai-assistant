<?php

namespace App\Http\Controllers;

use App\AI\Services\AIService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function sendMessage(Request $request): StreamedResponse
    {
        // Increase execution time for complex queries that make multiple API calls
        // Default PHP limit is often 30-60 seconds, which is too short when:
        // - Searching multiple emails
        // - Reading full email content
        // - Extracting information from multiple emails
        set_time_limit(300); // 5 minutes

        $message = $request->input('message');
        $history = $request->input('history', []);

        return response()->stream(function () use ($message, $history) {
            foreach (AIService::send($message, $history) as $chunk) {
                echo $chunk;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
