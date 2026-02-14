<?php

namespace App\Http\Controllers;

use App\Models\WebhookLog;
use App\Jobs\ProcessWebhookJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WebhookController extends Controller
{
    public function handle(Request $request, string $service): JsonResponse
    {
        if (!$this->isValidRequest($request)) {
            return response()->json(['success' => false, 'message' => 'Authentication failed'], 401);
        }

        $payload = $request->all();

        $webhookLog = WebhookLog::create([
            'service' => $service,
            'payload' => $payload,
            'status' => WebhookLog::STATUS_PENDING,
        ]);

        // Dispatch job to process webhook asynchronously
        ProcessWebhookJob::dispatch($webhookLog);

        return response()->json([
            'success' => true,
            'message' => 'Webhook received',
            'webhook_id' => $webhookLog->id,
        ], 200);
    }

    private function isValidRequest(Request $request)
    {
        $token = $request->header('X-Webhook-Token') ?? $request->input('token') ?? '';

        if (env('AGENT_TOKEN') === $token) {
            return true;
        }

        return false;
    }
}
