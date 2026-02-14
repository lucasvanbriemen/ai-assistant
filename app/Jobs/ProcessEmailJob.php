<?php

namespace App\Jobs;

use App\Models\WebhookLog;
use App\AI\Services\MemoryService;
use App\AI\Services\EventIngestionService;
use App\AI\Services\DataEnrichmentService;
use App\AI\Services\AutoMemoryExtractionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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
        try {
            // STEP 1: Ingest and normalize email data
            $ingestResult = EventIngestionService::processEmail($this->webhookLog->payload);
            if (!$ingestResult->success) {
                throw new \Exception("Failed to ingest email: " . $ingestResult->message);
            }
            $emailData = $ingestResult->data;

            // STEP 2: Enrich with entity data
            $enrichedData = DataEnrichmentService::enrichEmail($emailData);

            // STEP 3: Create/update person entity for sender
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

            // STEP 4: Use AI to extract structured information
            $content = "FROM: {$enrichedData['from']}\n";
            $content .= "SUBJECT: {$enrichedData['subject']}\n";
            $content .= "DATE: {$enrichedData['date']}\n\n";
            $content .= "BODY:\n{$enrichedData['body_clean']}";

            $extractionResult = AutoMemoryExtractionService::extract($content, [
                'source' => 'email',
                'sender' => $enrichedData['sender_name'],
            ]);

            if (!$extractionResult->success) {
                throw new \Exception("Failed to extract memories: " . $extractionResult->message);
            }

            $extracted = $extractionResult->data;

            // STEP 5: Store memory with extracted information
            $entityNames = array_merge(
                [$enrichedData['sender_name']],
                $extracted['people'] ?? []
            );

            $result = MemoryService::storeNote([
                'content' => $extracted['summary'] ?? $content,
                'type' => 'note',
                'entity_names' => array_filter(array_unique($entityNames)),
                'tags' => array_merge(['email'], $extracted['facts'] ?? []),
            ]);

            if ($result->success) {
                $this->webhookLog->markAsCompleted();
                Log::info("Email processed with AI extraction", [
                    'webhook_id' => $this->webhookLog->id,
                    'from' => $enrichedData['sender_name'],
                    'subject' => $enrichedData['subject'],
                    'extracted_people' => count($extracted['people'] ?? []),
                    'extracted_tasks' => count($extracted['tasks'] ?? []),
                ]);
            } else {
                throw new \Exception("Failed to store email: " . $result->message);
            }

        } catch (\Exception $e) {
            $this->webhookLog->markAsFailed($e->getMessage());
            Log::error("Failed to process email", [
                'webhook_id' => $this->webhookLog->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
