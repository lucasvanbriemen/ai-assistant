<?php

namespace App\AI\Services;

use App\AI\Contracts\ServiceResult;

class EventIngestionService
{
    public static function processEmail(array $rawData): ServiceResult
    {
        try {
            $normalized = [
                'from' => $rawData['from'] ?? $rawData['sender'] ?? 'unknown',
                'to' => $rawData['to'] ?? $rawData['recipient'] ?? null,
                'subject' => $rawData['subject'] ?? '(no subject)',
                'body' => $rawData['body'] ?? $rawData['content'] ?? '',
                'date' => $rawData['date'] ?? now()->toIso8601String(),
            ];

            $normalized['body_clean'] = strip_tags($normalized['body']);

            return ServiceResult::success($normalized);
        } catch (\Exception $e) {
            return ServiceResult::failure("Failed to process email: " . $e->getMessage());
        }
    }

    public static function processCalendarEvent(array $rawData): ServiceResult
    {
        try {
            $normalized = [
                'title' => $rawData['title'] ?? $rawData['summary'] ?? 'Untitled Event',
                'description' => $rawData['description'] ?? $rawData['notes'] ?? '',
                'start_time' => $rawData['start_time'] ?? $rawData['start'] ?? now()->toIso8601String(),
                'end_time' => $rawData['end_time'] ?? $rawData['end'] ?? null,
                'location' => $rawData['location'] ?? null,
                'attendees' => $rawData['attendees'] ?? [],
            ];

            return ServiceResult::success($normalized);
        } catch (\Exception $e) {
            return ServiceResult::failure("Failed to process calendar event: " . $e->getMessage());
        }
    }

    public static function processSlackMessage(array $rawData): ServiceResult
    {
        try {
            $event = $rawData['event'] ?? $rawData;

            $normalized = [
                'text' => $event['text'] ?? '',
                'user' => $event['user'] ?? 'unknown',
                'channel' => $event['channel'] ?? 'unknown',
                'type' => $event['type'] ?? 'message',
            ];

            return ServiceResult::success($normalized);
        } catch (\Exception $e) {
            return ServiceResult::failure("Failed to process Slack message: " . $e->getMessage());
        }
    }
}
