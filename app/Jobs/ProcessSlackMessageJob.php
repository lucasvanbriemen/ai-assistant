<?php

namespace App\Jobs;

use App\Models\WebhookLog;
use App\AI\Services\MemoryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessSlackMessageJob implements ShouldQueue
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
            $event = $payload['event'] ?? $payload;

            $text = $event['text'] ?? '';
            $user = $event['user'] ?? 'unknown';
            $channel = $event['channel'] ?? 'unknown';
            $type = $event['type'] ?? 'message';

            // Skip unimportant messages
            if (!$this->isImportant($event)) {
                Log::info("Skipping unimportant Slack message", ['webhook_id' => $this->webhookLog->id]);
                $this->webhookLog->markAsCompleted();
                return;
            }

            $content = "SLACK MESSAGE\nFROM: {$user}\nCHANNEL: {$channel}\n\nMESSAGE:\n{$text}";

            $result = MemoryService::storeNote([
                'content' => $content,
                'type' => 'note',
                'tags' => ['slack', $channel],
            ]);

            if ($result->success) {
                $this->webhookLog->markAsCompleted();
                Log::info("Slack message processed", ['webhook_id' => $this->webhookLog->id]);
            } else {
                throw new \Exception("Failed to store Slack message: " . $result->message);
            }

        } catch (\Exception $e) {
            $this->webhookLog->markAsFailed($e->getMessage());
            Log::error("Failed to process Slack message", ['webhook_id' => $this->webhookLog->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function isImportant(array $event): bool
    {
        $type = $event['type'] ?? '';
        $subtype = $event['subtype'] ?? null;

        if ($subtype === 'bot_message' || $subtype === 'channel_join' || $subtype === 'channel_leave') {
            return false;
        }

        if ($type === 'app_mention' || $type === 'message.im') {
            return true;
        }

        $text = strtolower($event['text'] ?? '');
        $keywords = ['decision', 'action', 'deadline', 'todo', 'asap', 'urgent', 'important'];
        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return $type === 'message' && !$subtype;
    }
}
