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
    /**
     * Handle incoming webhook for a specific service
     */
    public function handle(Request $request, string $service): JsonResponse
    {
        // Validate service is supported
        $supportedServices = ['email', 'calendar', 'slack', 'spotify', 'generic'];
        if (!in_array($service, $supportedServices)) {
            return response()->json([
                'success' => false,
                'message' => "Unsupported service: {$service}"
            ], 400);
        }

        // Authenticate request
        $authResult = $this->authenticateRequest($request, $service);
        if (!$authResult['success']) {
            Log::warning("Webhook authentication failed for service: {$service}", [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Authentication failed'
            ], 401);
        }

        // Get payload
        $payload = $request->all();

        // Special case: Slack URL verification challenge
        if ($service === 'slack' && isset($payload['type']) && $payload['type'] === 'url_verification') {
            return response()->json([
                'challenge' => $payload['challenge']
            ]);
        }

        // Validate payload is not empty
        if (empty($payload)) {
            return response()->json([
                'success' => false,
                'message' => 'Empty payload'
            ], 400);
        }

        // Log the webhook
        try {
            $webhookLog = WebhookLog::create([
                'service' => $service,
                'payload' => $payload,
                'status' => WebhookLog::STATUS_PENDING,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            Log::info("Webhook received for service: {$service}", [
                'webhook_log_id' => $webhookLog->id,
                'payload_size' => strlen(json_encode($payload)),
            ]);

            // Dispatch job to process webhook asynchronously
            ProcessWebhookJob::dispatch($webhookLog);

            return response()->json([
                'success' => true,
                'message' => 'Webhook received',
                'webhook_id' => $webhookLog->id,
            ], 200);

        } catch (\Exception $e) {
            Log::error("Failed to log webhook for service: {$service}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Authenticate webhook request using token
     */
    private function authenticateRequest(Request $request, string $service): array
    {
        // Get token from header or query param
        $token = $request->header('X-Webhook-Token')
               ?? $request->input('token')
               ?? null;

        if (!$token) {
            return ['success' => false, 'message' => 'No token provided'];
        }

        // Get expected token from env
        $expectedToken = $this->getServiceToken($service);

        if (!$expectedToken) {
            return ['success' => false, 'message' => 'Service not configured'];
        }

        // Compare tokens (timing-safe comparison)
        if (!hash_equals($expectedToken, $token)) {
            return ['success' => false, 'message' => 'Invalid token'];
        }

        return ['success' => true];
    }

    /**
     * Get webhook token for a service from environment
     */
    private function getServiceToken(string $service): ?string
    {
        $envKey = 'WEBHOOK_' . strtoupper($service) . '_TOKEN';
        return env($envKey);
    }

    /**
     * Get webhook statistics (for monitoring)
     */
    public function stats(Request $request): JsonResponse
    {
        $service = $request->input('service');

        $query = WebhookLog::query();

        if ($service) {
            $query->forService($service);
        }

        $stats = [
            'total' => $query->count(),
            'pending' => (clone $query)->pending()->count(),
            'completed' => (clone $query)->where('status', WebhookLog::STATUS_COMPLETED)->count(),
            'failed' => (clone $query)->failed()->count(),
            'by_service' => WebhookLog::selectRaw('service, count(*) as count')
                ->groupBy('service')
                ->pluck('count', 'service'),
            'recent_failures' => WebhookLog::failed()
                ->latest()
                ->limit(5)
                ->get(['id', 'service', 'error_message', 'created_at']),
        ];

        return response()->json($stats);
    }

    /**
     * Health check endpoint
     */
    public function health(): JsonResponse
    {
        try {
            // Check database connection
            $recentWebhooks = WebhookLog::latest()->limit(1)->count();

            return response()->json([
                'status' => 'healthy',
                'timestamp' => now()->toIso8601String(),
                'database' => 'connected',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toIso8601String(),
                'error' => $e->getMessage(),
            ], 503);
        }
    }
}
