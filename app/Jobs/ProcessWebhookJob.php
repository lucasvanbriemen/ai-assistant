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

    public function handle(): void
    {
        // Mark as processing
        $this->webhookLog->markAsProcessing();

        // Dispatch to appropriate service-specific job
        match ($this->webhookLog->service) {
            'email' => ProcessEmailJob::dispatch($this->webhookLog),
            default => throw new \Exception("Unknown service: {$this->webhookLog->service}")
        };
    }
}
