<?php

namespace App\AI\Plugins;

use App\AI\Contracts\ToolResult;
use App\AI\Contracts\ApiConfig;
use App\AI\Contracts\PluginInterface;
use App\AI\Services\MemoryService;

class MemoryPlugin extends PluginInterface
{
    private MemoryService $memoryService;

    public function __construct()
    {
        parent::__construct();
        $this->memoryService = app(MemoryService::class);
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
        $result = $this->memoryService->storePerson($params);

        return $this->returnResults($result);
    }

    private function storeNote(array $params): ToolResult
    {
        $result = $this->memoryService->storeNote($params);

        return $this->returnResults($result);
    }

    private function storeTranscript(array $params): ToolResult
    {
        $result = $this->memoryService->storeTranscript($params);

        return $this->returnResults($result);
    }

    private function storePreference(array $params): ToolResult
    {
        $result = $this->memoryService->storePreference($params);

        return $this->returnResults($result);
    }

    private function getPersonDetails(array $params): ToolResult
    {
        $result = $this->memoryService->getPersonDetails($params);

        return $this->returnResults($result);
    }

    private function createRelationship(array $params): ToolResult
    {
        $result = $this->memoryService->createRelationship($params);

        return $this->returnResults($result);
    }

    // ==================== RETRIEVAL METHODS ====================

    private function recallInformation(array $params): ToolResult
    {
        $result = $this->memoryService->recallInformation($params);

        return $this->returnResults($result);
    }

    private function getEntityDetails(array $params): ToolResult
    {
        $result = $this->memoryService->getEntityDetails($params);

        return $this->returnResults($result);
    }

    private function getUpcomingReminders(array $params): ToolResult
    {
        $result = $this->memoryService->getUpcomingReminders($params);

        return $this->returnResults($result);
    }

    private function listAllPeople(array $params): ToolResult
    {
        $result = $this->memoryService->listAllPeople($params);

        return $this->returnResults($result);
    }

    private function returnResults($result): ToolResult
    {
        return $result->success ? ToolResult::success($result->data) : ToolResult::failure($result->message);
    }
}
