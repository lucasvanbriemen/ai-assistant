<?php

namespace App\Jobs;

use App\AI\Services\MemoryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ExtractAndStoreMemoriesJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 90;

    public function __construct(
        public array $data,
        public string $source
    ) {}

    public function handle(): void
    {
        try {
            // For now, store content as a simple note
            // TODO: Use AutoMemoryExtractionService for AI-powered extraction (Step 1.4)

            $content = is_string($this->data) ? $this->data : json_encode($this->data, JSON_PRETTY_PRINT);

            $result = MemoryService::storeNote([
                'content' => $content,
                'type' => 'note',
                'tags' => [$this->source],
            ]);

            if ($result->success) {
                Log::info("Generic memory stored", ['source' => $this->source]);
            } else {
                throw new \Exception("Failed to store memory: " . $result->message);
            }

        } catch (\Exception $e) {
            Log::error("Failed to extract and store memory", [
                'source' => $this->source,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
