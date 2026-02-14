<?php

namespace App\AI\Services;

use App\Models\Memory;
use App\Models\MemoryEntity;
use App\Models\MemoryRelationship;
use App\AI\Contracts\ToolResult;
use Illuminate\Support\Facades\DB;

class MemoryService
{
    public static function storePerson(array $params): ToolResult
    {
        $entityData = [
            'name' => $params['name'],
            'entity_type' => 'person',
            'entity_subtype' => $params['entity_subtype'] ?? null,
            'description' => $params['description'] ?? null,
            'attributes' => $params['attributes'] ?? [],
            'start_date' => $params['start_date'] ?? null,
            'end_date' => $params['end_date'] ?? null,
        ];

        $entity = MemoryEntity::findOrCreateEntity($entityData);

        $action = $entity->wasRecentlyCreated ? 'stored' : 'updated';

        return ToolResult::success([
            'message' => "Successfully {$action} information about {$params['name']}",
            'entity_id' => $entity->id,
            'name' => $entity->name,
            'type' => $entity->entity_subtype ?? 'person',
            'attributes' => $entity->attributes,
        ]);
    }

    public static function storeNote(array $params): ToolResult
    {
        $memory = Memory::firstOrCreate(['content_hash' => hash('sha256', $params['content']), 'is_archived'  => false], [
            'type'            => $params['type'] ?? 'note',
            'content'         => $params['content'],
            'reminder_at'     => $params['reminder_at'] ?? null,
            'relevance_score' => 1.0,
        ]);

        if (!$memory->wasRecentlyCreated) {
            return ToolResult::success([
                'message'    => 'This note already exists in memory',
                'memory_id'  => $memory->id,
                'created_at' => $memory->created_at->toDateTimeString(),
            ]);
        }

        // Link entities if provided
        if (!empty($params['entity_names'])) {
            self::linkEntitiesToMemory($memory, $params['entity_names']);
        }

        // Attach tags if provided
        if (!empty($params['tags'])) {
            $memory->attachTags($params['tags']);
        }

        $message = 'Note stored successfully';
        if (!empty($params['reminder_at'])) {
            $message .= " with reminder set for {$params['reminder_at']}";
        }

        return ToolResult::success([
            'message' => $message,
            'memory_id' => $memory->id,
            'type' => $memory->type,
            'content_preview' => substr($memory->content, 0, 100),
        ]);
    }

    public static function storeTranscript(array $params): ToolResult
    {
        $content = $params['content'];
        $contentLength = mb_strlen($content);

        $summary = null;
        if ($contentLength > 1000) {
            $summary = substr($content, 0, 500) . '... [Transcript truncated]';
        }

        // Store metadata including title and date
        $metadata = [
            'title' => $params['title'],
            'date' => $params['date'] ?? now()->toDateString(),
            'attendee_count' => count($params['attendees'] ?? []),
        ];

        $memory = Memory::create([
            'type' => 'transcript',
            'content' => $content,
            'summary' => $summary,
            'metadata' => $metadata,
            'relevance_score' => 1.0,
        ]);

        // Link attendees
        if (!empty($params['attendees'])) {
            self::linkAttendeesToMemory($memory, $params['attendees']);
        }

        return ToolResult::success([
            'message' => "Transcript '{$params['title']}' stored successfully",
            'memory_id' => $memory->id,
            'content_length' => $contentLength,
            'attendees' => $params['attendees'] ?? [],
            'has_summary' => !empty($summary),
        ]);
    }

    public static function storePreference(array $params): ToolResult
    {
        $content = "{$params['category']}: {$params['value']}";
        if (!empty($params['notes'])) {
            $content .= " - {$params['notes']}";
        }

        // Check for duplicate preference
        $existing = Memory::where('type', 'preference')
            ->where('metadata->category', $params['category'])
            ->where('is_archived', false)
            ->first();

        if ($existing) {
            // Update existing preference
            $existing->update([
                'content' => $content,
                'metadata' => [
                    'category' => $params['category'],
                    'value' => $params['value'],
                    'notes' => $params['notes'] ?? null,
                ],
            ]);

            return ToolResult::success([
                'message' => "Updated preference for {$params['category']}",
                'memory_id' => $existing->id,
                'category' => $params['category'],
                'value' => $params['value'],
            ]);
        }

        // Create new preference
        $memory = Memory::create([
            'type' => 'preference',
            'content' => $content,
            'metadata' => [
                'category' => $params['category'],
                'value' => $params['value'],
                'notes' => $params['notes'] ?? null,
            ],
            'relevance_score' => 1.0,
        ]);


        return ToolResult::success([
            'message' => "Preference stored: {$params['category']} = {$params['value']}",
            'memory_id' => $memory->id,
            'category' => $params['category'],
            'value' => $params['value'],
        ]);
    }

    public static function createRelationship(array $params): ToolResult
    {
        // Find or create entities
        $fromEntity = MemoryEntity::findByName($params['from_entity_name']);
        $toEntity = MemoryEntity::findByName($params['to_entity_name']);

        if (!$fromEntity || !$toEntity) {
            DB::rollBack();
            return ToolResult::failure('One or both entities not found. Please store them first using store_person.');
        }

        // Create relationship
        $metadata = [];
        if (!empty($params['notes'])) {
            $metadata['notes'] = $params['notes'];
        }

        $relationship = MemoryRelationship::findOrCreate(
            $fromEntity->id,
            $toEntity->id,
            $params['relationship_type'],
            $metadata
        );

        return ToolResult::success([
            'message' => "Relationship created: {$params['from_entity_name']} {$params['relationship_type']} {$params['to_entity_name']}",
            'relationship_id' => $relationship->id,
            'from_entity' => $fromEntity->name,
            'to_entity' => $toEntity->name,
            'type' => $params['relationship_type'],
        ]);
    }

    public static function recallInformation(array $params): ToolResult
    {
        $query = $params['query'];
        $limit = $params['limit'] ?? 10;

        // Try semantic search first, fallback to full-text
        $results = Memory::searchSemantic($query, $limit);

        // Apply type filter if provided
        if (!empty($params['type'])) {
            $results = $results->filter(function ($memory) use ($params) {
                return $memory->type === $params['type'];
            })->take($limit);
        }

        // Record access for relevance tracking
        foreach ($results as $memory) {
            $memory->recordAccess();
        }

        if ($results->isEmpty()) {
            return ToolResult::success([
                'message' => 'No matching memories found',
                'query' => $query,
                'results' => [],
            ]);
        }

        $formattedResults = $results->map(function ($memory) {
            return [
                'id' => $memory->id,
                'type' => $memory->type,
                'content' => $memory->summary ?? $memory->content,
                'created_at' => $memory->created_at->toDateTimeString(),
                'relevance' => $memory->similarity_score ?? $memory->relevance_score,
            ];
        })->values()->all();

        return ToolResult::success([
            'message' => "Found {$results->count()} matching memories",
            'query' => $query,
            'results' => $formattedResults,
        ]);
    }

    public static function getPersonDetails(array $params): ToolResult
    {
        $entity = MemoryEntity::findByName($params['name'], 'person');

        if (!$entity) {
            return ToolResult::failure("Person '{$params['name']}' not found in memory");
        }

        // Record access
        $entity->recordMention();

        // Get full details
        $details = $entity->getFullDetails(20);

        return ToolResult::success([
            'message' => "Retrieved details for {$params['name']}",
            'person' => $details,
        ]);
    }

    public static function getEntityDetails(array $params): ToolResult
    {
        $name = $params['name'];
        $entityType = $params['entity_type'] ?? null;

        // Build query
        $query = MemoryEntity::where('name', 'LIKE', "%{$name}%")
            ->where('is_active', true);

        // Filter by entity_type if provided
        if ($entityType) {
            $query->where('entity_type', $entityType);
        }

        $entities = $query->get();

        if ($entities->isEmpty()) {
            $typeFilter = $entityType ? " (type: {$entityType})" : "";
            return ToolResult::failure("Entity '{$name}'{$typeFilter} not found in memory");
        }

        // If multiple matches, return all
        if ($entities->count() > 1) {
            $results = $entities->map(function ($entity) {
                return [
                    'id' => $entity->id,
                    'name' => $entity->name,
                    'type' => $entity->entity_type,
                    'subtype' => $entity->entity_subtype,
                    'description' => $entity->description,
                ];
            })->values()->all();

            return ToolResult::success([
                'message' => "Found {$entities->count()} entities matching '{$name}'",
                'multiple_matches' => true,
                'entities' => $results,
            ]);
        }

        // Single match - return full details
        $entity = $entities->first();
        $entity->recordMention();

        // Get full details including linked memories
        $details = $entity->getFullDetails(20);

        return ToolResult::success([
            'message' => "Retrieved details for {$entity->name} ({$entity->entity_type})",
            'entity' => $details,
        ]);
    }

    public static function getUpcomingReminders(array $params): ToolResult
    {
        $timeframe = $params['timeframe'] ?? 'all';
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;

        // Calculate dates based on timeframe
        if ($timeframe === 'today') {
            $startDate = now()->startOfDay();
            $endDate = now()->endOfDay();
        } elseif ($timeframe === 'tomorrow') {
            $startDate = now()->addDay()->startOfDay();
            $endDate = now()->addDay()->endOfDay();
        } elseif ($timeframe === 'this_week') {
            $startDate = now()->startOfWeek();
            $endDate = now()->endOfWeek();
        } elseif ($timeframe === 'this_month') {
            $startDate = now()->startOfMonth();
            $endDate = now()->endOfMonth();
        }

        $query = Memory::upcoming($startDate, $endDate)
            ->with(['entities', 'tags']);

        $reminders = $query->get();

        if ($reminders->isEmpty()) {
            return ToolResult::success([
                'message' => "No reminders found for {$timeframe}",
                'timeframe' => $timeframe,
                'results' => [],
            ]);
        }

        $formattedResults = $reminders->map(function ($memory) {
            return [
                'id' => $memory->id,
                'type' => $memory->type,
                'content' => $memory->content,
                'reminder_at' => $memory->reminder_at->toDateTimeString(),
                'entities' => $memory->entities->pluck('name')->all(),
                'tags' => $memory->tags->pluck('name')->all(),
            ];
        })->values()->all();

        return ToolResult::success([
            'message' => "Found {$reminders->count()} upcoming reminders",
            'timeframe' => $timeframe,
            'results' => $formattedResults,
        ]);
    }

    public static function listAllPeople(array $params): ToolResult
    {
        $limit = $params['limit'] ?? 50;
        $temporalFilter = $params['temporal_filter'] ?? 'current';

        $query = MemoryEntity::where('entity_type', 'person')
            ->where('is_active', true)
            ->orderByDesc('mention_count')
            ->limit($limit);

        // Apply entity subtype filter
        if (!empty($params['entity_subtype'])) {
            $query->where('entity_subtype', $params['entity_subtype']);
        }

        // Apply temporal filter
        switch ($temporalFilter) {
            case 'current':
                $query->current();
                break;
            case 'past':
                $query->past();
                break;
            case 'future':
                $query->future();
                break;
            case 'all':
                // No temporal filter - show all
                break;
        }

        $people = $query->get();

        if ($people->isEmpty()) {
            $filter = !empty($params['entity_subtype']) ? " ({$params['entity_subtype']})" : '';
            $temporal = $temporalFilter !== 'all' ? " {$temporalFilter}" : '';
            return ToolResult::success([
                'message' => "No{$temporal} people found{$filter}",
                'count' => 0,
                'results' => [],
            ]);
        }

        $formattedResults = $people->map(function ($entity) {
            return [
                'id' => $entity->id,
                'name' => $entity->name,
                'type' => $entity->entity_subtype ?? 'person',
                'description' => $entity->description,
                'attributes' => $entity->attributes,
                'mention_count' => $entity->mention_count,
                'last_mentioned' => $entity->last_mentioned_at?->toDateTimeString(),
                // Temporal information
                'start_date' => $entity->start_date?->toDateString(),
                'end_date' => $entity->end_date?->toDateString(),
                'is_current' => $entity->isCurrent(),
                'is_past' => $entity->isPast(),
            ];
        })->values()->all();

        $filter = !empty($params['entity_subtype']) ? " ({$params['entity_subtype']})" : '';
        $temporal = $temporalFilter !== 'all' ? " {$temporalFilter}" : '';

        return ToolResult::success([
            'message' => "Found {$people->count()}{$temporal} people{$filter}",
            'count' => $people->count(),
            'temporal_filter' => $temporalFilter,
            'results' => $formattedResults,
        ]);
    }

    private static function linkEntitiesToMemory(Memory $memory, array $entityNames): void
    {
        $entityIds = [];
        foreach ($entityNames as $name) {
            $entity = MemoryEntity::findByName($name);
            if ($entity) {
                $entityIds[] = $entity->id;
                $entity->recordMention();
            }
        }
        if (!empty($entityIds)) {
            $memory->attachEntities($entityIds);
        }
    }

    private static function linkAttendeesToMemory(Memory $memory, array $attendees): void
    {
        $entityIds = [];
        foreach ($attendees as $attendeeName) {
            // Find or create person entity
            $entity = MemoryEntity::findOrCreateEntity([
                'name' => $attendeeName,
                'entity_type' => 'person',
            ]);
            $entityIds[] = $entity->id;
            $entity->recordMention();
        }
        $memory->attachEntities($entityIds, 'attendee');
    }
}
