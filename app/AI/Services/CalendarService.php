<?php

namespace App\AI\Services;

use App\AI\Contracts\ToolResult;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;

class CalendarService
{
    private static function getCalendarService(): Calendar|ToolResult
    {
        $credentialsPath = base_path(config('services.google.service_account_file'));

        if (!file_exists($credentialsPath)) {
            return ToolResult::failure('Google service account credentials file not found. Place your JSON key file at: ' . $credentialsPath);
        }

        $client = new GoogleClient();
        $client->setAuthConfig($credentialsPath);
        $client->addScope(Calendar::CALENDAR);

        return new Calendar($client);
    }

    private static function getCalendarIds(): array
    {
        $ids = config('services.google.calendar_ids', '');
        return array_filter(array_map('trim', explode(',', $ids)));
    }

    public static function listEvents(array $params): ToolResult
    {
        $service = self::getCalendarService();
        if ($service instanceof ToolResult) {
            return $service;
        }

        $calendarIds = self::getCalendarIds();
        if (empty($calendarIds)) {
            return ToolResult::failure('No calendar IDs configured. Set GOOGLE_CALENDAR_IDS in .env (comma-separated email addresses).');
        }

        $optParams = [
            'singleEvents' => true,
            'orderBy' => 'startTime',
            'maxResults' => $params['limit'] ?? 10,
        ];

        if (!empty($params['time_min'])) {
            $optParams['timeMin'] = self::toRfc3339($params['time_min']);
        } else {
            $optParams['timeMin'] = now()->toRfc3339String();
        }

        if (!empty($params['time_max'])) {
            $optParams['timeMax'] = self::toRfc3339($params['time_max']);
        }

        if (!empty($params['query'])) {
            $optParams['q'] = $params['query'];
        }

        // If a specific calendar is requested, only query that one
        if (!empty($params['calendar_id'])) {
            $calendarIds = [$params['calendar_id']];
        }

        $allEvents = [];
        foreach ($calendarIds as $calendarId) {
            $events = $service->events->listEvents($calendarId, $optParams);
            foreach ($events->getItems() as $event) {
                $formatted = self::formatEvent($event);
                $formatted['calendar_id'] = $calendarId;
                $allEvents[] = $formatted;
            }
        }

        // Sort all events by start time
        usort($allEvents, fn($a, $b) => strcmp($a['start_time'], $b['start_time']));

        // Apply limit across all calendars
        $limit = $params['limit'] ?? 10;
        $allEvents = array_slice($allEvents, 0, $limit);

        return ToolResult::success([
            'message' => 'Found ' . count($allEvents) . ' event(s)',
            'events' => $allEvents,
        ]);
    }

    public static function getEvent(array $params): ToolResult
    {
        $service = self::getCalendarService();
        if ($service instanceof ToolResult) {
            return $service;
        }

        if (empty($params['event_id'])) {
            return ToolResult::failure('event_id is required');
        }

        $calendarId = $params['calendar_id'] ?? self::getCalendarIds()[0] ?? null;
        if (!$calendarId) {
            return ToolResult::failure('No calendar_id provided and no default configured.');
        }

        $event = $service->events->get($calendarId, $params['event_id']);

        return ToolResult::success([
            'message' => 'Event details retrieved',
            'event' => self::formatEvent($event),
        ]);
    }

    public static function createEvent(array $params): ToolResult
    {
        $service = self::getCalendarService();
        if ($service instanceof ToolResult) {
            return $service;
        }

        if (empty($params['title']) || empty($params['start_time']) || empty($params['end_time'])) {
            return ToolResult::failure('title, start_time, and end_time are required');
        }

        $calendarId = $params['calendar_id'] ?? self::getCalendarIds()[0] ?? null;
        if (!$calendarId) {
            return ToolResult::failure('No calendar_id provided and no default configured.');
        }

        $timezone = $params['timezone'] ?? 'Europe/Amsterdam';

        $event = new Event([
            'summary' => $params['title'],
            'description' => $params['description'] ?? null,
            'location' => $params['location'] ?? null,
            'start' => [
                'dateTime' => self::toRfc3339($params['start_time']),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => self::toRfc3339($params['end_time']),
                'timeZone' => $timezone,
            ],
        ]);

        if (!empty($params['attendees'])) {
            $attendees = array_map(fn($email) => ['email' => $email], $params['attendees']);
            $event->setAttendees($attendees);
        }

        $created = $service->events->insert($calendarId, $event);

        return ToolResult::success([
            'message' => "Event '{$params['title']}' created successfully",
            'event' => self::formatEvent($created),
        ]);
    }

    public static function updateEvent(array $params): ToolResult
    {
        $service = self::getCalendarService();
        if ($service instanceof ToolResult) {
            return $service;
        }

        if (empty($params['event_id'])) {
            return ToolResult::failure('event_id is required');
        }

        $calendarId = $params['calendar_id'] ?? self::getCalendarIds()[0] ?? null;
        if (!$calendarId) {
            return ToolResult::failure('No calendar_id provided and no default configured.');
        }

        $event = $service->events->get($calendarId, $params['event_id']);
        $timezone = $params['timezone'] ?? $event->getStart()->getTimeZone() ?? 'Europe/Amsterdam';

        if (!empty($params['title'])) {
            $event->setSummary($params['title']);
        }

        if (array_key_exists('description', $params)) {
            $event->setDescription($params['description']);
        }

        if (array_key_exists('location', $params)) {
            $event->setLocation($params['location']);
        }

        if (!empty($params['start_time'])) {
            $start = new EventDateTime();
            $start->setDateTime(self::toRfc3339($params['start_time']));
            $start->setTimeZone($timezone);
            $event->setStart($start);
        }

        if (!empty($params['end_time'])) {
            $end = new EventDateTime();
            $end->setDateTime(self::toRfc3339($params['end_time']));
            $end->setTimeZone($timezone);
            $event->setEnd($end);
        }

        if (!empty($params['attendees'])) {
            $attendees = array_map(fn($email) => ['email' => $email], $params['attendees']);
            $event->setAttendees($attendees);
        }

        $updated = $service->events->update($calendarId, $params['event_id'], $event);

        return ToolResult::success([
            'message' => 'Event updated successfully',
            'event' => self::formatEvent($updated),
        ]);
    }

    public static function deleteEvent(array $params): ToolResult
    {
        $service = self::getCalendarService();
        if ($service instanceof ToolResult) {
            return $service;
        }

        if (empty($params['event_id'])) {
            return ToolResult::failure('event_id is required');
        }

        $calendarId = $params['calendar_id'] ?? self::getCalendarIds()[0] ?? null;
        if (!$calendarId) {
            return ToolResult::failure('No calendar_id provided and no default configured.');
        }

        $service->events->delete($calendarId, $params['event_id']);

        return ToolResult::success([
            'message' => 'Event deleted successfully',
            'event_id' => $params['event_id'],
        ]);
    }

    private static function formatEvent(Event $event): array
    {
        $start = $event->getStart();
        $end = $event->getEnd();

        return [
            'event_id' => $event->getId(),
            'title' => $event->getSummary(),
            'description' => $event->getDescription(),
            'location' => $event->getLocation(),
            'start_time' => $start->getDateTime() ?? $start->getDate(),
            'end_time' => $end->getDateTime() ?? $end->getDate(),
            'timezone' => $start->getTimeZone(),
            'status' => $event->getStatus(),
            'html_link' => $event->getHtmlLink(),
            'attendees' => array_map(function ($a) {
                return [
                    'email' => $a->getEmail(),
                    'name' => $a->getDisplayName(),
                    'status' => $a->getResponseStatus(),
                ];
            }, $event->getAttendees() ?? []),
        ];
    }

    private static function toRfc3339(string $datetime): string
    {
        $parsed = new \DateTime($datetime);
        return $parsed->format(\DateTime::RFC3339);
    }
}
