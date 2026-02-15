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

    public function __construct(
        public WebhookLog $webhookLog
    ) {}

    public function handle(): void
    {
        $emailData = $this->webhookLog->payload;
        $enrichedData = DataEnrichmentService::enrichEmail($emailData);

        // Extract structured info from email body FIRST so we can enrich the entity
        $content = "FROM: {$enrichedData['from']}\n";
        $content .= "SUBJECT: {$enrichedData['subject']}\n";
        $content .= "DATE: {$enrichedData['date']}\n\n";
        $content .= "BODY:\n{$enrichedData['body_clean']}";

        $extracted = AutoMemoryExtractionService::extract($content, [
            'source' => 'email',
            'sender' => $enrichedData['sender_name'],
        ]);

        // Store/update sender entity with enriched data from extraction
        if ($enrichedData['sender_name'] && $enrichedData['sender_email']) {
            $attributes = [
                'email' => $enrichedData['sender_email'],
            ];

            // Merge contact details extracted from email body (phone, title, company, etc.)
            $senderDetails = $extracted['contact_details'][$enrichedData['sender_name']] ?? [];
            if (!empty($senderDetails)) {
                $attributes = array_merge($attributes, $senderDetails);
            }

            $personData = [
                'name' => $enrichedData['sender_name'],
                'entity_subtype' => 'contact',
                'attributes' => $attributes,
            ];

            // Build description from extracted facts that mention the sender
            $senderFacts = array_filter(
                $extracted['facts'] ?? [],
                fn ($fact) => stripos($fact, $enrichedData['sender_name']) !== false
                    || stripos($fact, explode(' ', $enrichedData['sender_name'])[0]) !== false
            );
            if (!empty($senderFacts)) {
                $personData['description'] = implode('. ', $senderFacts);
            }

            if (isset($enrichedData['existing_entity'])) {
                $personData = DataEnrichmentService::mergeEntityData(
                    $enrichedData['existing_entity'],
                    $personData,
                );
            }

            MemoryService::storePerson($personData);
        }

        // Store memory note and link all mentioned people (including new ones)
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
