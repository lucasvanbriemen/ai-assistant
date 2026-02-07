# Calendar Plugin API Contracts

This document defines the expected request and response formats for the Calendar API endpoints that the AI agent will call.

## Base Configuration

- **Base URL**: Configured via `CALENDAR_API_BASE_URL` env variable (default: `http://localhost:3000`)
- **Authentication**: Bearer token via `CALENDAR_API_AUTH_TOKEN` env variable (optional)
- **Content-Type**: `application/json`

---

## Endpoints

### 1. Get Events
**Endpoint**: `GET /api/calendar/events`

**Query Parameters**:
```json
{
  "from_date": "optional - YYYY-MM-DD format, start of date range",
  "to_date": "optional - YYYY-MM-DD format, end of date range"
}
```

**Example Request**:
```
GET /api/calendar/events?from_date=2026-02-06&to_date=2026-02-06
Authorization: Bearer {CALENDAR_API_AUTH_TOKEN}
```

**Expected Response** (HTTP 200):
```json
{
  "count": 2,
  "events": [
    {
      "id": "event_001",
      "title": "Team Lunch",
      "description": "Team lunch at Italian restaurant",
      "start_time": "2026-02-13 12:00",
      "end_time": "2026-02-13 13:30",
      "location": "Downtown Italian Restaurant"
    },
    {
      "id": "event_002",
      "title": "Cinema Night",
      "description": "Watch the new Marvel movie",
      "start_time": "2026-02-14 19:00",
      "end_time": "2026-02-14 22:00",
      "location": "Downtown Cinema, Theater 5"
    }
  ]
}
```

**Response Fields**:
- `count` (integer): Total number of matching events
- `events` (array): List of calendar events
  - `id` (string): Unique event identifier
  - `title` (string): Event title/name
  - `description` (string, optional): Event description
  - `start_time` (string): Event start time in `YYYY-MM-DD HH:MM` format
  - `end_time` (string): Event end time in `YYYY-MM-DD HH:MM` format
  - `location` (string, optional): Event location

---

### 2. Create Event
**Endpoint**: `POST /api/calendar/events`

**Request Body**:
```json
{
  "title": "required - Event title",
  "start_time": "required - YYYY-MM-DD or YYYY-MM-DD HH:MM format",
  "end_time": "required - YYYY-MM-DD or YYYY-MM-DD HH:MM format",
  "description": "optional - Event description",
  "location": "optional - Event location"
}
```

**Example Request**:
```
POST /api/calendar/events
Authorization: Bearer {CALENDAR_API_AUTH_TOKEN}
Content-Type: application/json

{
  "title": "Project Planning Meeting",
  "description": "Discuss Q1 roadmap and milestones",
  "start_time": "2026-02-10 14:00",
  "end_time": "2026-02-10 15:30",
  "location": "Conference Room B"
}
```

**Expected Response** (HTTP 200):
```json
{
  "message": "Event created successfully",
  "event": {
    "id": "event_1707345600_5432",
    "title": "Project Planning Meeting",
    "description": "Discuss Q1 roadmap and milestones",
    "start_time": "2026-02-10 14:00",
    "end_time": "2026-02-10 15:30",
    "location": "Conference Room B",
    "created_at": "2026-02-07 10:30:00"
  }
}
```

**Response Fields**:
- `message` (string): Confirmation message
- `event` (object): The created event
  - `id` (string): Unique event identifier
  - `title` (string): Event title
  - `description` (string, optional): Event description
  - `start_time` (string): Start time in `YYYY-MM-DD HH:MM` format
  - `end_time` (string): End time in `YYYY-MM-DD HH:MM` format
  - `location` (string, optional): Event location
  - `created_at` (string): ISO 8601 or `YYYY-MM-DD HH:MM:SS` timestamp

---

### 3. Update Event
**Endpoint**: `PUT /api/calendar/events/{id}`

**Path Parameters**:
- `id` (string): The event ID to update

**Request Body** (all fields optional):
```json
{
  "title": "optional - New event title",
  "description": "optional - New event description",
  "start_time": "optional - YYYY-MM-DD HH:MM format",
  "end_time": "optional - YYYY-MM-DD HH:MM format",
  "location": "optional - New event location"
}
```

**Example Request**:
```
PUT /api/calendar/events/event_1707345600_5432
Authorization: Bearer {CALENDAR_API_AUTH_TOKEN}
Content-Type: application/json

{
  "title": "Project Planning Meeting - RESCHEDULED",
  "location": "Virtual - Zoom Link"
}
```

**Expected Response** (HTTP 200):
```json
{
  "message": "Event updated successfully",
  "event": {
    "id": "event_1707345600_5432",
    "title": "Project Planning Meeting - RESCHEDULED",
    "description": "Discuss Q1 roadmap and milestones",
    "start_time": "2026-02-10 14:00",
    "end_time": "2026-02-10 15:30",
    "location": "Virtual - Zoom Link",
    "updated_at": "2026-02-07 10:35:00"
  }
}
```

**Response Fields**:
- `message` (string): Confirmation message
- `event` (object): The updated event with all current fields
  - `updated_at` (string, optional): ISO 8601 or `YYYY-MM-DD HH:MM:SS` timestamp

**Error Response** (HTTP 404):
```json
{
  "error": "Event with ID 'event_1707345600_5432' not found"
}
```

---

### 4. Delete Event
**Endpoint**: `DELETE /api/calendar/events/{id}`

**Path Parameters**:
- `id` (string): The event ID to delete

**Example Request**:
```
DELETE /api/calendar/events/event_1707345600_5432
Authorization: Bearer {CALENDAR_API_AUTH_TOKEN}
```

**Expected Response** (HTTP 200):
```json
{
  "message": "Event deleted successfully"
}
```

**Error Response** (HTTP 404):
```json
{
  "error": "Event with ID 'event_1707345600_5432' not found"
}
```

---

## Error Handling

All endpoints should return appropriate HTTP status codes:

- **200 OK**: Request successful
- **400 Bad Request**: Invalid parameters or missing required fields
- **401 Unauthorized**: Invalid or missing authentication token
- **404 Not Found**: Resource (event) not found
- **500 Internal Server Error**: Server error

Error response format:
```json
{
  "error": "Human-readable error message"
}
```

---

## Authentication

If `CALENDAR_API_AUTH_TOKEN` is configured, it will be sent as:
```
Authorization: Bearer {CALENDAR_API_AUTH_TOKEN}
```

If not configured, the Authorization header will be omitted.

---

## Data Type Notes

- **Dates**: Use `YYYY-MM-DD` format for date-only values
- **DateTime**: Use `YYYY-MM-DD HH:MM` format for date + time (24-hour format)
- **Timestamps**: Use ISO 8601 format for created_at/updated_at
- **Event IDs**: Can be any unique string (UUID, database ID, custom format)
- **Special Characters**: Descriptions and titles can contain newlines and special characters

---

## Filtering Notes

When both `from_date` and `to_date` are provided:
- Events are filtered to those falling within the date range (inclusive)
- The plugin compares the event's start_time date against the range
- Example: `from_date=2026-02-10&to_date=2026-02-15` returns events on those dates and in between

When neither date parameter is provided:
- Return all events

---

## Example Implementation Checklist

- [ ] `GET /api/calendar/events` - Get events with optional date filtering
- [ ] `POST /api/calendar/events` - Create new event
- [ ] `PUT /api/calendar/events/{id}` - Update existing event
- [ ] `DELETE /api/calendar/events/{id}` - Delete event
- [ ] All responses return proper JSON structure
- [ ] Error responses include error message
- [ ] Authentication via Bearer token (if token configured)
- [ ] Proper HTTP status codes (200, 400, 404, etc.)
- [ ] DateTime format is consistent (`YYYY-MM-DD HH:MM`)
- [ ] Proper date filtering in GET endpoint
