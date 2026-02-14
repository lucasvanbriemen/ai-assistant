<?php

namespace App\AI\Services;

class EventIngestionService
{
    public static function processEmail(array $rawData)
    {
        $normalized = [
            'from' => $rawData['from'] ?? $rawData['sender'] ?? 'unknown',
            'to' => $rawData['to'] ?? $rawData['recipient'] ?? null,
            'subject' => $rawData['subject'] ?? '(no subject)',
            'body' => $rawData['body'] ?? $rawData['content'] ?? '',
            'date' => $rawData['date'] ?? now()->toIso8601String(),
        ];

        $normalized['body_clean'] = strip_tags($normalized['body']);

        return $normalized;
    }
}
