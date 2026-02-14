<?php

namespace App\Jobs;

use App\AI\Services\MemoryService;
use App\AI\Services\AutoMemoryExtractionService;
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
            // Convert data to string content
            $content = is_string($this->data) ? $this->data : json_encode($this->data, JSON_PRETTY_PRINT);

            // Use AI to extract structured information
            $extractionResult = AutoMemoryExtractionService::extract($content, [
                'source' => $this->source,
            ]);

            if (!$extractionResult->success) {
                throw new \Exception("Failed to extract memories: " . $extractionResult->message);
            }

            $extracted = $extractionResult->data;

            // Store memory with extracted information
            $result = MemoryService::storeNote([
                'content' => $extracted['summary'] ?? $content,
                'type' => 'note',
                'entity_names' => $extracted['people'] ?? [],
                'tags' => array_merge([$this->source], array_slice($extracted['facts'] ?? [], 0, 5)),
            ]);

            if ($result->success) {
                Log::info("Memory stored with AI extraction", [
                    'source' => $this->source,
                    'extracted_people' => count($extracted['people'] ?? []),
                    'extracted_tasks' => count($extracted['tasks'] ?? []),
                    'importance' => $extracted['importance'] ?? 0.5,
                ]);
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
