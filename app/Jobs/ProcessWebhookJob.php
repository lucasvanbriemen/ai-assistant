<?php

namespace App\Jobs;

use App\Models\WebhookLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessWebhookJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public WebhookLog $webhookLog
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Mark as processing
            $this->webhookLog->markAsProcessing();

            Log::info("Processing webhook", [
                'id' => $this->webhookLog->id,
                'service' => $this->webhookLog->service,
            ]);

            // Dispatch to appropriate service-specific job
            match ($this->webhookLog->service) {
                'email' => ProcessEmailJob::dispatch($this->webhookLog),
                'calendar' => ProcessCalendarEventJob::dispatch($this->webhookLog),
                'slack' => ProcessSlackMessageJob::dispatch($this->webhookLog),
                'spotify', 'generic' => ExtractAndStoreMemoriesJob::dispatch(
                    $this->webhookLog->payload,
                    $this->webhookLog->service
                ),
                default => throw new \Exception("Unknown service: {$this->webhookLog->service}")
            };

            // Mark as completed (service-specific job will handle actual completion)
            // For now, just log success
            Log::info("Webhook dispatched to service job", [
                'id' => $this->webhookLog->id,
                'service' => $this->webhookLog->service,
            ]);

        } catch (\Exception $e) {
            // Mark as failed
            $this->webhookLog->markAsFailed($e->getMessage());

            Log::error("Failed to process webhook", [
                'id' => $this->webhookLog->id,
                'service' => $this->webhookLog->service,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->webhookLog->markAsFailed("Job failed after {$this->tries} attempts: " . $exception->getMessage());

        Log::critical("Webhook job permanently failed", [
            'id' => $this->webhookLog->id,
            'service' => $this->webhookLog->service,
            'error' => $exception->getMessage(),
        ]);
    }
}
