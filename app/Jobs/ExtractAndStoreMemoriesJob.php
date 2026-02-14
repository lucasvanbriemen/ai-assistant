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
    }
}
