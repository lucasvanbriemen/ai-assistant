<?php

namespace App\Http\Controllers;

use App\AI\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Handle incoming emails from external email service
 * Process them with AI to take actions (create calendar events, etc.)
 */
class EmailWebhookController extends Controller
{
    public function __construct(
        private AIService $aiService,
    ) {}

    /**
     * Receive an incoming email and process it with AI
     * The AI will analyze the email and take appropriate actions
     * (e.g., create calendar events, add reminders, etc.)
     */
    public function handleIncoming(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|string',
            'subject' => 'required|string',
            'sender' => 'required|email',
            'date' => 'required|string',
            'body' => 'required|string',
        ]);

        try {
            // Build a message for the AI to analyze the email
            $emailSummary = $this->formatEmailForAnalysis($validated);

            $systemInstruction = <<<EOT
An incoming email has been received. Analyze it and take appropriate actions:
- If it mentions dates, times, or events, create calendar events
- If it requires a response, suggest what to do
- Extract any actionable items or deadlines
- Be proactive in creating calendar entries for meetings, appointments, or events mentioned

Here is the incoming email:

{$emailSummary}

Based on this email, perform any necessary actions (create calendar events, etc.) and summarize what you did.
EOT;

            // Process with AI
            $response = $this->aiService->chat($systemInstruction, []);

            if (!$response['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $response['error'],
                    'email_id' => $validated['id'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'email_id' => $validated['id'],
                'subject' => $validated['subject'],
                'sender' => $validated['sender'],
                'ai_analysis' => $response['message'],
                'tools_used' => $response['tools_used'] ?? [],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to process email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format email for AI analysis
     */
    private function formatEmailForAnalysis(array $email): string
    {
        return <<<EOT
From: {$email['sender']}
Subject: {$email['subject']}
Date: {$email['date']}

{$email['body']}
EOT;
    }
}
