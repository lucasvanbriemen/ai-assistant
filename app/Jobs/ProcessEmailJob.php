<?php

namespace App\Jobs;

use App\Models\WebhookLog;
use App\AI\Services\MemoryService;
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
            $payload = $this->webhookLog->payload;

            // Extract email data
            $from = $payload['from'] ?? $payload['sender'] ?? 'unknown';
            $to = $payload['to'] ?? $payload['recipient'] ?? null;
            $subject = $payload['subject'] ?? '(no subject)';
            $body = $payload['body'] ?? $payload['content'] ?? '';
            $date = $payload['date'] ?? now()->toIso8601String();

            // Build content for AI extraction
            $content = "FROM: {$from}\n";
            if ($to) {
                $content .= "TO: {$to}\n";
            }
            $content .= "SUBJECT: {$subject}\n";
            $content .= "DATE: {$date}\n\n";
            $content .= "BODY:\n{$body}";

            // Extract sender name and email
            $senderEmail = $this->extractEmail($from);
            $senderName = $this->extractName($from, $senderEmail);

            // Create/update person entity for sender
            if ($senderName && $senderEmail) {
                MemoryService::storePerson([
                    'name' => $senderName,
                    'entity_subtype' => 'contact',
                    'description' => "Email contact",
                    'attributes' => [
                        'email' => $senderEmail,
                    ],
                ]);
            }

            // Store as note with AI extraction
            // TODO: Use AutoMemoryExtractionService (Step 1.4)
            // For now, store directly
            $result = MemoryService::storeNote([
                'content' => "Email from {$senderName}: {$subject}\n\n{$body}",
                'type' => 'note',
                'entity_names' => $senderName ? [$senderName] : [],
                'tags' => ['email'],
            ]);

            if ($result->success) {
                $this->webhookLog->markAsCompleted();
                Log::info("Email processed successfully", [
                    'webhook_id' => $this->webhookLog->id,
                    'from' => $from,
                    'subject' => $subject,
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

    /**
     * Extract email address from string like "John Doe <john@example.com>"
     */
    private function extractEmail(string $from): ?string
    {
        if (preg_match('/<(.+?)>/', $from, $matches)) {
            return $matches[1];
        }
        if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return $from;
        }
        return null;
    }

    /**
     * Extract name from string like "John Doe <john@example.com>"
     */
    private function extractName(string $from, ?string $email): ?string
    {
        $name = preg_replace('/<.+?>/', '', $from);
        $name = trim($name);

        if (empty($name) && $email) {
            // Extract name from email (before @)
            $name = explode('@', $email)[0];
            $name = str_replace(['.', '_', '-'], ' ', $name);
            $name = ucwords($name);
        }

        return $name ?: null;
    }
}
