<?php

namespace App\AI\Services;

use App\Models\MemoryEntity;
use Illuminate\Support\Facades\Http;

class DataEnrichmentService
{
    public static function enrichEmail(array $emailData): array
    {
        $enriched = $emailData;

        $existingEntity = MemoryEntity::where('email', $emailData['sender_email'])
            ->orWhere(function ($q) use ($emailData) {
                $q->where('name', 'LIKE', $emailData['sender_name'])
                    ->where('entity_type', 'person')
                    ->where('is_active', true);
            })
            ->first();

        if ($existingEntity) {
            $enriched['sender_entity_id'] = $existingEntity->id;
            $enriched['existing_entity'] = $existingEntity;
        }

        return $enriched;
    }

    public static function mergeEntityData(MemoryEntity $existing, array $newData): array
    {
        $existingData = [
            'name' => $existing->name,
            'entity_subtype' => $existing->entity_subtype,
            'description' => $existing->description,
            'email' => $existing->email,
            'phone' => $existing->phone,
            'attributes' => $existing->attributes ?? [],
        ];

        $prompt = self::buildMergePrompt($existingData, $newData);

        $response = Http::withToken(config('ai.openai.api_key'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'You merge entity data. Return ONLY valid JSON. Never lose existing information. Only add or improve, never downgrade.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.1,
                'max_tokens' => 300,
            ]);

        $result = $response->json();
        $merged = json_decode($result['choices'][0]['message']['content'] ?? '{}', true);

        if (empty($merged) || empty($merged['name'])) {
            return $existingData;
        }

        return $merged;
    }

    private static function buildMergePrompt(array $existing, array $new): string
    {
        $existingJson = json_encode($existing, JSON_PRETTY_PRINT);
        $newJson = json_encode($new, JSON_PRETTY_PRINT);

        return <<<PROMPT
            Merge these two records for the same person. Keep ALL existing information. Only add new information or improve vague fields with more specific data. Never replace a detailed value with a generic one.

            EXISTING (higher priority):
            {$existingJson}

            NEW (from email):
            {$newJson}

            Return the merged result as JSON with these fields:
            {
                "name": "Best known name",
                "entity_subtype": "Most specific subtype (e.g. keep 'colleague' over 'contact')",
                "description": "Most comprehensive description combining both sources",
                "attributes": {"merged attributes object"}
            }

            Rules:
            - If existing description is detailed, keep it (append new info if relevant)
            - If existing subtype is more specific than new, keep existing
            - Merge attributes from both, existing values take priority
            - Never return null for fields that have existing values

            Return ONLY the JSON, no other text.
        PROMPT;
    }
}
