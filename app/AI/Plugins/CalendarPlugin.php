<?php

namespace App\AI\Plugins;

use App\AI\Contracts\PluginInterface;
use App\AI\Contracts\ToolDefinition;
use App\AI\Contracts\ToolResult;

class CalendarPlugin implements PluginInterface
{
    private array $events = [];

    public function __construct()
    {
        $this->loadMockEvents();
    }

    public function getName(): string
    {
        return 'calendar';
    }

    public function getDescription(): string
    {
        return 'Manage your calendar events and schedule';
    }

    public function getTools(): array
    {
        return [
            new ToolDefinition(
                name: 'get_events',
                description: 'Get calendar events in a date range',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'from_date' => [
                            'type' => 'string',
                            'description' => 'Start date for event search (YYYY-MM-DD)',
                        ],
                        'to_date' => [
                            'type' => 'string',
                            'description' => 'End date for event search (YYYY-MM-DD)',
                        ],
                    ],
                    'required' => [],
                ],
                category: 'read'
            ),

            new ToolDefinition(
                name: 'create_event',
                description: 'Create a new calendar event',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Event title',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Event description',
                        ],
                        'start_time' => [
                            'type' => 'string',
                            'description' => 'Event start time (YYYY-MM-DD HH:MM or YYYY-MM-DD)',
                        ],
                        'end_time' => [
                            'type' => 'string',
                            'description' => 'Event end time (YYYY-MM-DD HH:MM or YYYY-MM-DD)',
                        ],
                        'location' => [
                            'type' => 'string',
                            'description' => 'Event location',
                        ],
                    ],
                    'required' => ['title', 'start_time', 'end_time'],
                ],
                category: 'write'
            ),

            new ToolDefinition(
                name: 'update_event',
                description: 'Update an existing calendar event',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'event_id' => [
                            'type' => 'string',
                            'description' => 'The event ID to update',
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'New event title',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'New event description',
                        ],
                        'start_time' => [
                            'type' => 'string',
                            'description' => 'New start time (YYYY-MM-DD HH:MM)',
                        ],
                        'end_time' => [
                            'type' => 'string',
                            'description' => 'New end time (YYYY-MM-DD HH:MM)',
                        ],
                        'location' => [
                            'type' => 'string',
                            'description' => 'New location',
                        ],
                    ],
                    'required' => ['event_id'],
                ],
                category: 'write'
            ),

            new ToolDefinition(
                name: 'delete_event',
                description: 'Delete a calendar event',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'event_id' => [
                            'type' => 'string',
                            'description' => 'The event ID to delete',
                        ],
                    ],
                    'required' => ['event_id'],
                ],
                category: 'write'
            ),
        ];
    }

    public function executeTool(string $toolName, array $parameters): ToolResult
    {
        return match ($toolName) {
            'get_events' => $this->getEvents($parameters),
            'create_event' => $this->createEvent($parameters),
            'update_event' => $this->updateEvent($parameters),
            'delete_event' => $this->deleteEvent($parameters),
            default => ToolResult::failure("Tool '{$toolName}' not found in CalendarPlugin"),
        };
    }

    private function getEvents(array $params): ToolResult
    {
        $fromDate = $params['from_date'] ?? null;
        $toDate = $params['to_date'] ?? null;

        $results = [];

        foreach ($this->events as $event) {
            $eventDate = substr($event['start_time'], 0, 10);

            if ($fromDate && $eventDate < $fromDate) {
                continue;
            }
            if ($toDate && $eventDate > $toDate) {
                continue;
            }

            $results[] = [
                'id' => $event['id'],
                'title' => $event['title'],
                'description' => $event['description'] ?? null,
                'start_time' => $event['start_time'],
                'end_time' => $event['end_time'],
                'location' => $event['location'] ?? null,
            ];
        }

        return ToolResult::success([
            'count' => count($results),
            'events' => $results,
        ]);
    }

    private function createEvent(array $params): ToolResult
    {
        $newEvent = [
            'id' => 'event_' . time() . '_' . rand(1000, 9999),
            'title' => $params['title'],
            'description' => $params['description'] ?? null,
            'start_time' => $this->normalizeDateTime($params['start_time']),
            'end_time' => $this->normalizeDateTime($params['end_time']),
            'location' => $params['location'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->events[] = $newEvent;

        return ToolResult::success([
            'message' => 'Event created successfully',
            'event' => $newEvent,
        ]);
    }

    private function updateEvent(array $params): ToolResult
    {
        $eventId = $params['event_id'];

        foreach ($this->events as &$event) {
            if ($event['id'] === $eventId) {
                if (isset($params['title'])) {
                    $event['title'] = $params['title'];
                }
                if (isset($params['description'])) {
                    $event['description'] = $params['description'];
                }
                if (isset($params['start_time'])) {
                    $event['start_time'] = $this->normalizeDateTime($params['start_time']);
                }
                if (isset($params['end_time'])) {
                    $event['end_time'] = $this->normalizeDateTime($params['end_time']);
                }
                if (isset($params['location'])) {
                    $event['location'] = $params['location'];
                }
                $event['updated_at'] = date('Y-m-d H:i:s');

                return ToolResult::success([
                    'message' => 'Event updated successfully',
                    'event' => $event,
                ]);
            }
        }

        return ToolResult::failure("Event with ID '{$eventId}' not found");
    }

    private function deleteEvent(array $params): ToolResult
    {
        $eventId = $params['event_id'];

        foreach ($this->events as $index => $event) {
            if ($event['id'] === $eventId) {
                unset($this->events[$index]);
                return ToolResult::success(['message' => 'Event deleted successfully']);
            }
        }

        return ToolResult::failure("Event with ID '{$eventId}' not found");
    }

    private function normalizeDateTime(string $dateTime): string
    {
        // If only date is provided (YYYY-MM-DD), add default time
        if (strlen($dateTime) === 10) {
            return $dateTime . ' 00:00';
        }
        return $dateTime;
    }

    private function loadMockEvents(): void
    {
        $this->events = [
            [
                'id' => 'event_001',
                'title' => 'Team Meeting',
                'description' => 'Weekly team standup and project updates',
                'start_time' => '2026-02-10 10:00',
                'end_time' => '2026-02-10 11:00',
                'location' => 'Conference Room A',
            ],
            [
                'id' => 'event_002',
                'title' => 'Doctor Appointment',
                'description' => 'Annual checkup with Dr. Smith',
                'start_time' => '2026-02-10 15:00',
                'end_time' => '2026-02-10 16:00',
                'location' => 'Health Center Clinic',
            ],
            [
                'id' => 'event_003',
                'title' => 'Cinema Night',
                'description' => 'Watch the new Marvel movie',
                'start_time' => '2026-02-14 19:00',
                'end_time' => '2026-02-14 22:00',
                'location' => 'Downtown Cinema, Theater 5',
            ],
            [
                'id' => 'event_004',
                'title' => 'Team Lunch',
                'description' => 'Team lunch at Italian restaurant',
                'start_time' => '2026-02-13 12:00',
                'end_time' => '2026-02-13 13:30',
                'location' => 'Downtown Italian Restaurant',
            ],
            [
                'id' => 'event_005',
                'title' => 'Project Deadline',
                'description' => 'Submit final project deliverables',
                'start_time' => '2026-02-20 17:00',
                'end_time' => '2026-02-20 17:00',
                'location' => null,
            ],
            [
                'id' => 'event_006',
                'title' => 'Holiday Trip',
                'description' => '2-week trip to Spain, staying at Barcelona Beach Resort',
                'start_time' => '2026-02-20 08:00',
                'end_time' => '2026-03-06 18:00',
                'location' => 'Barcelona, Spain',
            ],
        ];
    }
}
