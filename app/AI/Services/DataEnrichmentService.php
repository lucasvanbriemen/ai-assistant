<?php

namespace App\AI\Services;

use App\Models\MemoryEntity;

class DataEnrichmentService
{
    public static function enrichEmail(array $emailData): array
    {
        $enriched = $emailData;

        $email = self::extractEmail($emailData['from']);
        $name = self::extractName($emailData['from'], $email);

        $enriched['sender_email'] = $email;
        $enriched['sender_name'] = $name;

        $existingEntity = MemoryEntity::where('email', $email)->first();
        if ($existingEntity) {
            $enriched['sender_entity_id'] = $existingEntity->id;
        }

        return $enriched;
    }

    public static function enrichCalendarEvent(array $eventData): array
    {
        $enriched = $eventData;
        $enriched['attendee_entities'] = [];

        foreach ($eventData['attendees'] ?? [] as $attendee) {
            $email = self::extractEmail($attendee);
            $name = self::extractName($attendee, $email);

            if ($name) {
                $enriched['attendee_entities'][] = [
                    'name' => $name,
                    'email' => $email,
                ];
            }
        }

        return $enriched;
    }

    private static function extractEmail(string $text): ?string
    {
        if (preg_match('/<(.+?)>/', $text, $matches)) {
            return $matches[1];
        }
        if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
            return $text;
        }
        return null;
    }

    private static function extractName(string $text, ?string $email): ?string
    {
        $name = preg_replace('/<.+?>/', '', $text);
        $name = trim($name);

        if (empty($name) && $email) {
            $name = explode('@', $email)[0];
            $name = str_replace(['.', '_', '-'], ' ', $name);
            $name = ucwords($name);
        }

        return $name ?: null;
    }
}
