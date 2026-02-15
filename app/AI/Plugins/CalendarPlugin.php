<?php

namespace App\AI\Plugins;

use App\AI\Contracts\ToolResult;
use App\AI\Contracts\ApiConfig;
use App\AI\Contracts\PluginInterface;
use App\AI\Services\CalendarService;

class CalendarPlugin extends PluginInterface
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function getApiConfig(): ApiConfig
    {
        return new ApiConfig();
    }

    public function getTools()
    {
        return [
            [
                'name' => 'list_calendar_events',
                'description' => 'List upcoming calendar events from all connected calendars. Can filter by time range, search query, specific calendar, and limit.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'time_min' => [
                            'type' => 'string',
                            'description' => 'Start of time range (ISO 8601 format, e.g. 2026-02-15T00:00:00). Defaults to now.',
                        ],
                        'time_max' => [
                            'type' => 'string',
                            'description' => 'End of time range (ISO 8601 format, e.g. 2026-02-15T23:59:59)',
                        ],
                        'query' => [
                            'type' => 'string',
                            'description' => 'Free-text search query to filter events',
                        ],
                        'calendar_id' => [
                            'type' => 'string',
                            'description' => 'Optional: specific calendar email to query. If omitted, queries all configured calendars.',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of events to return (default: 10)',
                        ],
                    ],
                    'required' => [],
                ],
            ],

            [
                'name' => 'get_calendar_event',
                'description' => 'Get detailed information about a specific calendar event by its ID',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'event_id' => [
                            'type' => 'string',
                            'description' => 'The Google Calendar event ID',
                        ],
                        'calendar_id' => [
                            'type' => 'string',
                            'description' => 'Calendar email address (defaults to first configured calendar)',
                        ],
                    ],
                    'required' => ['event_id'],
                ],
            ],

            [
                'name' => 'create_calendar_event',
                'description' => 'Create a new calendar event',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Event title/summary',
                        ],
                        'start_time' => [
                            'type' => 'string',
                            'description' => 'Event start time (ISO 8601 format, e.g. 2026-02-16T15:00:00)',
                        ],
                        'end_time' => [
                            'type' => 'string',
                            'description' => 'Event end time (ISO 8601 format, e.g. 2026-02-16T16:00:00)',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Event description or notes',
                        ],
                        'location' => [
                            'type' => 'string',
                            'description' => 'Event location',
                        ],
                        'timezone' => [
                            'type' => 'string',
                            'description' => 'Timezone (default: Europe/Amsterdam)',
                        ],
                        'attendees' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'List of attendee email addresses',
                        ],
                        'calendar_id' => [
                            'type' => 'string',
                            'description' => 'Calendar email address to create the event in (defaults to first configured calendar)',
                        ],
                    ],
                    'required' => ['title', 'start_time', 'end_time'],
                ],
            ],

            [
                'name' => 'update_calendar_event',
                'description' => 'Update an existing calendar event. Only provided fields will be changed.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'event_id' => [
                            'type' => 'string',
                            'description' => 'The Google Calendar event ID to update',
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'New event title/summary',
                        ],
                        'start_time' => [
                            'type' => 'string',
                            'description' => 'New start time (ISO 8601 format)',
                        ],
                        'end_time' => [
                            'type' => 'string',
                            'description' => 'New end time (ISO 8601 format)',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'New event description',
                        ],
                        'location' => [
                            'type' => 'string',
                            'description' => 'New event location',
                        ],
                        'timezone' => [
                            'type' => 'string',
                            'description' => 'Timezone for the event',
                        ],
                        'attendees' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Updated list of attendee email addresses',
                        ],
                        'calendar_id' => [
                            'type' => 'string',
                            'description' => 'Calendar email address (defaults to first configured calendar)',
                        ],
                    ],
                    'required' => ['event_id'],
                ],
            ],

            [
                'name' => 'delete_calendar_event',
                'description' => 'Delete a calendar event by its ID',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'event_id' => [
                            'type' => 'string',
                            'description' => 'The Google Calendar event ID to delete',
                        ],
                        'calendar_id' => [
                            'type' => 'string',
                            'description' => 'Calendar email address (defaults to first configured calendar)',
                        ],
                    ],
                    'required' => ['event_id'],
                ],
            ],
        ];
    }

    public function executeTool(string $toolName, array $parameters)
    {
        return match ($toolName) {
            'list_calendar_events' => CalendarService::listEvents($parameters),
            'get_calendar_event' => CalendarService::getEvent($parameters),
            'create_calendar_event' => CalendarService::createEvent($parameters),
            'update_calendar_event' => CalendarService::updateEvent($parameters),
            'delete_calendar_event' => CalendarService::deleteEvent($parameters),
            default => ToolResult::failure("Tool '{$toolName}' not found in CalendarPlugin"),
        };
    }
}
