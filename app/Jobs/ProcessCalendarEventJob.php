<?php

namespace App\Jobs;

use App\Models\WebhookLog;
use App\AI\Services\MemoryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessCalendarEventJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 90;

    public function __construct(
        public WebhookLog $webhookLog
    ) {}

    public function handle(): void
    {
        try {
            $payload = $this->webhookLog->payload;

            $title = $payload['title'] ?? $payload['summary'] ?? 'Untitled Event';
            $description = $payload['description'] ?? $payload['notes'] ?? '';
            $startTime = $payload['start_time'] ?? $payload['start'] ?? now()->toIso8601String();
            $location = $payload['location'] ?? null;
            $attendees = $payload['attendees'] ?? [];

            $content = "MEETING: {$title}\nDATE: {$startTime}\n";
            if ($location) $content .= "LOCATION: {$location}\n";
            if (!empty($attendees)) $content .= "ATTENDEES: " . implode(', ', $attendees) . "\n";
            if ($description) $content .= "\nDESCRIPTION:\n{$description}";

            $entityNames = [];
            foreach ($attendees as $attendee) {
                $name = trim(preg_replace('/<.+?>/', '', $attendee));
                if ($name) {
                    $entityNames[] = $name;
                    MemoryService::storePerson([
                        'name' => $name,
                        'entity_subtype' => 'colleague',
                        'description' => "Met in meetings",
                    ]);
                }
            }

            $result = MemoryService::storeTranscript([
                'content' => $content,
                'title' => $title,
                'attendees' => $entityNames,
                'date' => date('Y-m-d', strtotime($startTime)),
            ]);

            if ($result->success) {
                $this->webhookLog->markAsCompleted();
                Log::info("Calendar event processed", ['webhook_id' => $this->webhookLog->id]);
            } else {
                throw new \Exception("Failed to store calendar event: " . $result->message);
            }

        } catch (\Exception $e) {
            $this->webhookLog->markAsFailed($e->getMessage());
            Log::error("Failed to process calendar event", ['webhook_id' => $this->webhookLog->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
