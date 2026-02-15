<?php

namespace App\Jobs;

use App\Models\WebhookLog;
use App\AI\Services\AIService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public WebhookLog $webhookLog
    ) {}

    public function handle(): void
    {
        $emailData = $this->webhookLog->payload;

        $content = "You received an email. Analyze it and take all appropriate actions.\n\n";
        $content .= "FROM: {$emailData['sender_name']} <{$emailData['sender_email']}>\n";
        $content .= "SUBJECT: {$emailData['subject']}\n";
        $content .= "DATE: {$emailData['date']}\n\n";
        $content .= "BODY:\n{$emailData['body_clean']}";

        AIService::processAutonomously($content);

        $this->webhookLog->markAsCompleted();
    }
}
