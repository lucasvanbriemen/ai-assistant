<?php

namespace App\Jobs;

use App\Models\WebhookLog;
use App\AI\Services\MemoryService;
use App\AI\Services\DataEnrichmentService;
use App\AI\Services\AutoMemoryExtractionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessEmailJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 90;

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
        $emailData = $this->webhookLog->payload;
        $enrichedData = DataEnrichmentService::enrichEmail($emailData);

        if ($enrichedData['sender_name'] && $enrichedData['sender_email']) {
            MemoryService::storePerson([
                'name' => $enrichedData['sender_name'],
                'entity_subtype' => 'contact',
                'description' => "Email contact",
                'attributes' => [
                    'email' => $enrichedData['sender_email'],
                ],
            ]);
        }

        $content = "FROM: {$enrichedData['from']}\n";
        $content .= "SUBJECT: {$enrichedData['subject']}\n";
        $content .= "DATE: {$enrichedData['date']}\n\n";
        $content .= "BODY:\n{$enrichedData['body_clean']}";

        $extracted = AutoMemoryExtractionService::extract($content, [
            'source' => 'email',
            'sender' => $enrichedData['sender_name'],
        ]);

        $entityNames = array_merge(
            [$enrichedData['sender_name']],
            $extracted['people'] ?? []
        );

        MemoryService::storeNote([
            'content' => $extracted['summary'] ?? $content,
            'type' => 'note',
            'entity_names' => array_filter(array_unique($entityNames)),
            'tags' => array_merge(['email'], $extracted['facts'] ?? []),
        ]);

        $this->webhookLog->markAsCompleted();
    }
}
