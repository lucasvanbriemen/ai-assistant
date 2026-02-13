<?php

namespace App\AI\Plugins;

use App\AI\Contracts\ToolResult;
use App\AI\Contracts\ApiConfig;
use App\AI\Contracts\PluginInterface;
use App\Models\Memory;
use App\Models\MemoryEntity;
use App\Models\MemoryRelationship;
use App\Models\MemoryTag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MemoryPlugin extends PluginInterface
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function getApiConfig(): ApiConfig
    {
        // No external API needed - uses database directly
        return new ApiConfig(
            baseUrl: '',
            endpoints: []
        );
    }

    public function getName()
    {
        return 'memory';
    }

    public function getDescription()
    {
        return 'Store and retrieve personal information, people, notes, reminders, preferences, and meeting transcripts';
    }

    public function getTools()
    {
        return [
            // ========== STORAGE TOOLS ==========
            [
                'name' => 'store_person',
                'description' => 'Store or update information about a person (colleague, family, friend, contact)',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Full name of the person',
                        ],
                        'entity_subtype' => [
                            'type' => 'string',
                            'description' => 'Type of person: colleague, family, friend, contact, etc.',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Brief description or context about the person',
                        ],
                        'attributes' => [
                            'type' => 'object',
                            'description' => 'Flexible attributes like job_title, company, department, phone, email, birthday, address, relationship, etc.',
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'description' => 'When this relationship/role started (YYYY-MM-DD). E.g., when they became a colleague, friend, or started at a company',
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'description' => 'When this relationship/role ended or will end (YYYY-MM-DD). E.g., quit date, friendship ended, etc.',
                        ],
                    ],
                    'required' => ['name'],
                ],
            ],

            [
                'name' => 'store_note',
                'description' => 'Store a note, fact, or reminder. Can link to entities (people, places, organizations)',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'content' => [
                            'type' => 'string',
                            'description' => 'The note content',
                        ],
                        'type' => [
                            'type' => 'string',
                            'description' => 'Type: note, reminder, fact, idea, task',
                            'enum' => ['note', 'reminder', 'fact', 'idea', 'task'],
                        ],
                        'reminder_at' => [
                            'type' => 'string',
                            'description' => 'Optional reminder date/time (ISO 8601 format: YYYY-MM-DD HH:MM:SS)',
                        ],
                        'entity_names' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Names of people/entities mentioned in this note',
                        ],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Tags for categorization (work, personal, hobby, etc.)',
                        ],
                    ],
                    'required' => ['content'],
                ],
            ],

            [
                'name' => 'store_transcript',
                'description' => 'Store a meeting transcript or long-form document with attendees',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'content' => [
                            'type' => 'string',
                            'description' => 'The full transcript or document content',
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Title or subject of the meeting/document',
                        ],
                        'attendees' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Names of meeting attendees or people mentioned',
                        ],
                        'date' => [
                            'type' => 'string',
                            'description' => 'Date of the meeting (YYYY-MM-DD)',
                        ],
                    ],
                    'required' => ['content', 'title'],
                ],
            ],

            [
                'name' => 'store_preference',
                'description' => 'Store user preferences, subscriptions, or settings',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'category' => [
                            'type' => 'string',
                            'description' => 'Preference category: tool, service, food, music, hobby, subscription, etc.',
                        ],
                        'value' => [
                            'type' => 'string',
                            'description' => 'The preference value or description',
                        ],
                        'notes' => [
                            'type' => 'string',
                            'description' => 'Additional notes or context',
                        ],
                    ],
                    'required' => ['category', 'value'],
                ],
            ],

            [
                'name' => 'create_relationship',
                'description' => 'Create a relationship between two entities (e.g., John works_at Acme Corp)',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'from_entity_name' => [
                            'type' => 'string',
                            'description' => 'Name of the first entity',
                        ],
                        'to_entity_name' => [
                            'type' => 'string',
                            'description' => 'Name of the second entity',
                        ],
                        'relationship_type' => [
                            'type' => 'string',
                            'description' => 'Type of relationship: works_at, lives_at, reports_to, friend_of, member_of, etc.',
                        ],
                        'notes' => [
                            'type' => 'string',
                            'description' => 'Additional context about the relationship',
                        ],
                    ],
                    'required' => ['from_entity_name', 'to_entity_name', 'relationship_type'],
                ],
            ],

            // ========== RETRIEVAL TOOLS ==========
            [
                'name' => 'recall_information',
                'description' => 'Search memories using natural language. Supports semantic search for intelligent retrieval',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Search query in natural language',
                        ],
                        'type' => [
                            'type' => 'string',
                            'description' => 'Optional filter by type: note, reminder, fact, preference, transcript',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of results (default: 10)',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],

            [
                'name' => 'get_person_details',
                'description' => 'Get detailed information about a specific person including all related memories',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Name of the person to look up',
                        ],
                    ],
                    'required' => ['name'],
                ],
            ],

            [
                'name' => 'get_upcoming_reminders',
                'description' => 'Get upcoming reminders and time-based tasks',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'timeframe' => [
                            'type' => 'string',
                            'description' => 'Timeframe: today, tomorrow, this_week, this_month, or custom date range',
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'description' => 'Start date for custom range (YYYY-MM-DD)',
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'description' => 'End date for custom range (YYYY-MM-DD)',
                        ],
                    ],
                    'required' => [],
                ],
            ],

            [
                'name' => 'list_all_people',
                'description' => 'List all people with optional filtering by type (colleagues, family, friends) and temporal status (current, past, future)',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'entity_subtype' => [
                            'type' => 'string',
                            'description' => 'Optional filter: colleague, family, friend, contact',
                        ],
                        'temporal_filter' => [
                            'type' => 'string',
                            'description' => 'Filter by temporal status: current (default), past, future, all',
                            'enum' => ['current', 'past', 'future', 'all'],
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of results (default: 50)',
                        ],
                    ],
                    'required' => [],
                ],
            ],

            [
                'name' => 'get_entity_details',
                'description' => 'Get detailed information about ANY entity (pet, vehicle, book, place, service, project, etc.) including all related memories. Use this for non-person entities.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Name of the entity to look up (e.g., "Max", "Tesla Model 3", "Netflix")',
                        ],
                        'entity_type' => [
                            'type' => 'string',
                            'description' => 'Optional: Type of entity (pet, vehicle, book, place, service, project, etc.) to narrow search',
                        ],
                    ],
                    'required' => ['name'],
                ],
            ],
        ];
    }

    public function executeTool(string $toolName, array $parameters)
    {
        return match ($toolName) {
            'store_person' => $this->storePerson($parameters),
            'store_note' => $this->storeNote($parameters),
            'store_transcript' => $this->storeTranscript($parameters),
            'store_preference' => $this->storePreference($parameters),
            'create_relationship' => $this->createRelationship($parameters),
            'recall_information' => $this->recallInformation($parameters),
            'get_person_details' => $this->getPersonDetails($parameters),
            'get_upcoming_reminders' => $this->getUpcomingReminders($parameters),
            'list_all_people' => $this->listAllPeople($parameters),
            'get_entity_details' => $this->getEntityDetails($parameters),
            default => ToolResult::failure("Tool '{$toolName}' not found in MemoryPlugin"),
        };
    }

    // ==================== STORAGE METHODS ====================

    private function storePerson(array $params): ToolResult
    {
        try {
            DB::beginTransaction();

            $entityData = [
                'name' => $params['name'],
                'entity_type' => 'person',
                'entity_subtype' => $params['entity_subtype'] ?? null,
                'description' => $params['description'] ?? null,
                'attributes' => $params['attributes'] ?? [],
            ];

            // Add temporal tracking if provided
            if (isset($params['start_date'])) {
                $entityData['start_date'] = $params['start_date'];
            }
            if (isset($params['end_date'])) {
                $entityData['end_date'] = $params['end_date'];
            }

            $entity = MemoryEntity::findOrCreateEntity($entityData);

            DB::commit();

            // Clear relevant caches
            try {
                Cache::tags(['entities'])->flush();
            } catch (\BadMethodCallException $e) {
                Cache::flush();
            }

            $action = $entity->wasRecentlyCreated ? 'stored' : 'updated';

            return ToolResult::success([
                'message' => "Successfully {$action} information about {$params['name']}",
                'entity_id' => $entity->id,
                'name' => $entity->name,
                'type' => $entity->entity_subtype ?? 'person',
                'attributes' => $entity->attributes,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to store person', ['error' => $e->getMessage(), 'params' => $params]);
            return ToolResult::failure('Failed to store person information: ' . $e->getMessage());
        }
    }

    private function storeNote(array $params): ToolResult
    {
        try {
            DB::beginTransaction();

            // Check for duplicate
            $duplicate = Memory::findDuplicate($params['content']);
            if ($duplicate) {
                DB::commit();
                return ToolResult::success([
                    'message' => 'This note already exists in memory',
                    'memory_id' => $duplicate->id,
                    'created_at' => $duplicate->created_at->toDateTimeString(),
                ]);
            }

            $memory = Memory::create([
                'type' => $params['type'] ?? 'note',
                'content' => $params['content'],
                'reminder_at' => !empty($params['reminder_at']) ? $params['reminder_at'] : null,
                'relevance_score' => 1.0,
            ]);

            // Link entities if provided
            if (!empty($params['entity_names'])) {
                $entityIds = [];
                foreach ($params['entity_names'] as $name) {
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

            // Attach tags if provided
            if (!empty($params['tags'])) {
                $memory->attachTags($params['tags']);
            }

            DB::commit();

            // Clear caches
            try {
                Cache::tags(['memory', 'search'])->flush();
            } catch (\BadMethodCallException $e) {
                Cache::flush();
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

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to store note', ['error' => $e->getMessage(), 'params' => $params]);
            return ToolResult::failure('Failed to store note: ' . $e->getMessage());
        }
    }

    private function storeTranscript(array $params): ToolResult
    {
        try {
            DB::beginTransaction();

            $content = $params['content'];
            $contentLength = mb_strlen($content);

            // Generate summary for long content (would use AI in production)
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
                $entityIds = [];
                foreach ($params['attendees'] as $attendeeName) {
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

            DB::commit();

            // Clear caches
            try {
                Cache::tags(['memory', 'search'])->flush();
            } catch (\BadMethodCallException $e) {
                Cache::flush();
            }

            return ToolResult::success([
                'message' => "Transcript '{$params['title']}' stored successfully",
                'memory_id' => $memory->id,
                'content_length' => $contentLength,
                'attendees' => $params['attendees'] ?? [],
                'has_summary' => !empty($summary),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to store transcript', ['error' => $e->getMessage()]);
            return ToolResult::failure('Failed to store transcript: ' . $e->getMessage());
        }
    }

    private function storePreference(array $params): ToolResult
    {
        try {
            DB::beginTransaction();

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
                DB::commit();

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

            DB::commit();

            // Clear caches
            try {
                Cache::tags(['memory', 'search'])->flush();
            } catch (\BadMethodCallException $e) {
                Cache::flush();
            }

            return ToolResult::success([
                'message' => "Preference stored: {$params['category']} = {$params['value']}",
                'memory_id' => $memory->id,
                'category' => $params['category'],
                'value' => $params['value'],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to store preference', ['error' => $e->getMessage(), 'params' => $params]);
            return ToolResult::failure('Failed to store preference: ' . $e->getMessage());
        }
    }

    private function createRelationship(array $params): ToolResult
    {
        try {
            DB::beginTransaction();

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

            DB::commit();

            // Clear caches
            try {
                Cache::tags(['relationships'])->flush();
            } catch (\BadMethodCallException $e) {
                Cache::flush();
            }

            return ToolResult::success([
                'message' => "Relationship created: {$params['from_entity_name']} {$params['relationship_type']} {$params['to_entity_name']}",
                'relationship_id' => $relationship->id,
                'from_entity' => $fromEntity->name,
                'to_entity' => $toEntity->name,
                'type' => $params['relationship_type'],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create relationship', ['error' => $e->getMessage(), 'params' => $params]);
            return ToolResult::failure('Failed to create relationship: ' . $e->getMessage());
        }
    }

    // ==================== RETRIEVAL METHODS ====================

    private function recallInformation(array $params): ToolResult
    {
        try {
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

        } catch (\Exception $e) {
            Log::error('Failed to recall information', ['error' => $e->getMessage(), 'params' => $params]);
            return ToolResult::failure('Failed to search memories: ' . $e->getMessage());
        }
    }

    private function getPersonDetails(array $params): ToolResult
    {
        try {
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

        } catch (\Exception $e) {
            Log::error('Failed to get person details', ['error' => $e->getMessage(), 'params' => $params]);
            return ToolResult::failure('Failed to retrieve person details: ' . $e->getMessage());
        }
    }

    private function getEntityDetails(array $params): ToolResult
    {
        try {
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

        } catch (\Exception $e) {
            Log::error('Failed to get entity details', ['error' => $e->getMessage(), 'params' => $params]);
            return ToolResult::failure('Failed to retrieve entity details: ' . $e->getMessage());
        }
    }

    private function getUpcomingReminders(array $params): ToolResult
    {
        try {
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

        } catch (\Exception $e) {
            Log::error('Failed to get reminders', ['error' => $e->getMessage(), 'params' => $params]);
            return ToolResult::failure('Failed to retrieve reminders: ' . $e->getMessage());
        }
    }

    private function listAllPeople(array $params): ToolResult
    {
        try {
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

        } catch (\Exception $e) {
            Log::error('Failed to list people', ['error' => $e->getMessage(), 'params' => $params]);
            return ToolResult::failure('Failed to list people: ' . $e->getMessage());
        }
    }
}
