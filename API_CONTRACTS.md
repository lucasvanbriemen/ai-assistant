# Email Plugin API Contracts

This document defines the expected request and response formats for the Email API endpoints that the AI agent will call.

## Base Configuration

- **Base URL**: Configured via `EMAIL_API_BASE_URL` env variable (default: `http://localhost:3000`)
- **Authentication**: Bearer token via `EMAIL_API_AUTH_TOKEN` env variable (optional)
- **Content-Type**: `application/json`

---

## Endpoints

### 1. Search Emails
**Endpoint**: `GET /api/emails/search`

**Query Parameters**:
```json
{
  "keyword": "optional - search in subject and body",
  "sender": "optional - filter by sender email address",
  "from_date": "optional - YYYY-MM-DD format",
  "to_date": "optional - YYYY-MM-DD format",
  "unread_only": "optional - boolean, only return unread emails",
  "limit": "optional - maximum number of results to return (default: 50, max: 100)"
}
```

**Example Request**:
```
GET /api/emails/search?keyword=lunch&from_date=2026-02-06&to_date=2026-02-06
Authorization: Bearer {EMAIL_API_AUTH_TOKEN}
```

**Expected Response** (HTTP 200):
```json
{
  "count": 2,
  "emails": [
    {
      "id": "email_unique_id_1",
      "subject": "Team Lunch This Friday",
      "sender": "hr@company.com",
      "date": "2026-02-05",
      "preview": "Hi Everyone, We're organizing a team lunch this Friday at noon at the Italian restaurant downtown..."
    },
    {
      "id": "email_unique_id_2",
      "subject": "Lunch Plans",
      "sender": "john@company.com",
      "date": "2026-02-06",
      "preview": "Let's grab lunch at the Italian place downtown..."
    }
  ]
}
```

**Response Fields**:
- `count` (integer): Total number of matching emails
- `emails` (array): List of email previews (max 10 returned by plugin)
  - `id` (string): Unique email identifier
  - `subject` (string): Email subject
  - `sender` (string): Sender email address
  - `date` (string): Email date in YYYY-MM-DD format
  - `preview` (string): First ~100 characters of email body
  - `unread` (boolean, optional): Whether email is unread

---

### 2. Read Full Email
**Endpoint**: `GET /api/emails/{id}`

**Path Parameters**:
- `id` (string): The email ID to retrieve

**Example Request**:
```
GET /api/emails/email_unique_id_1
Authorization: Bearer {EMAIL_API_AUTH_TOKEN}
```

**Expected Response** (HTTP 200):
```json
{
  "id": "email_unique_id_1",
  "subject": "Team Lunch This Friday",
  "sender": "hr@company.com",
  "date": "2026-02-05",
  "body": "Hi Everyone,\n\nWe're organizing a team lunch this Friday at noon at the Italian restaurant downtown. Please RSVP with your dietary preferences.\n\nLooking forward to seeing you there!"
}
```

**Response Fields**:
- `id` (string): Email ID
- `subject` (string): Email subject
- `sender` (string): Sender email address
- `date` (string): Email date in YYYY-MM-DD format
- `body` (string): Full email body (can contain newlines)

**Error Response** (HTTP 404):
```json
{
  "error": "Email with ID 'email_unique_id_1' not found"
}
```

---

### 3. Extract Email Information
**Tool**: `extract_email_info` (Internal tool - reads email and extracts structured fields)

**Parameters**:
```json
{
  "email_id": "required - the email ID to extract information from",
  "fields": ["required - array of field names to extract", "e.g., ['movie_title', 'date', 'time', 'location', 'confirmation_number']"]
}
```

**Supported Fields**:
- `movie_title`, `film_title` - Extract movie/film titles from email
- `date`, `event_date` - Extract event dates
- `time`, `event_time` - Extract event times
- `location`, `venue` - Extract locations/venues
- `seat`, `seats` - Extract seat information
- `confirmation_number`, `booking_number`, `reservation_number` - Extract confirmation/booking numbers
- Any custom field name (will attempt to find it in the email)

**Example Request** (via AI):
When the AI detects it needs movie information, it will call:
```json
{
  "email_id": "email_123",
  "fields": ["movie_title", "date", "time", "location"]
}
```

**Expected Response**:
```json
{
  "success": true,
  "email_id": "email_123",
  "extracted_fields": {
    "movie_title": "Avatar: The Way of Water",
    "date": "31 January 2026",
    "time": "22:00",
    "location": "Pathé De Kuip - Rotterdam"
  }
}
```

---

## Error Handling

All endpoints should return appropriate HTTP status codes:

- **200 OK**: Request successful
- **400 Bad Request**: Invalid parameters or missing required fields
- **401 Unauthorized**: Invalid or missing authentication token
- **404 Not Found**: Resource (email) not found
- **500 Internal Server Error**: Server error

Error response format:
```json
{
  "error": "Human-readable error message"
}
```

---

## Authentication

If `EMAIL_API_AUTH_TOKEN` is configured, it will be sent as:
```
Authorization: Bearer {EMAIL_API_AUTH_TOKEN}
```

If not configured, the Authorization header will be omitted.

---

## Data Type Notes

- **Dates**: Use `YYYY-MM-DD` format for date parameters and responses
- **Timestamps**: Use ISO 8601 format for sent dates and timestamps
- **Email IDs**: Can be any unique string (UUID, database ID, custom format)
- **Email Addresses**: Standard email format (user@domain.com)
- **Special Characters**: Email body can contain newlines (`\n`) and should handle HTML if needed

---

## Example Implementation Checklist

- [ ] `/api/emails/search` - GET with query filters
- [ ] `/api/emails/{id}` - GET full email by ID
- [ ] `/api/emails/unread/count` - GET unread count
- [ ] `/api/emails/send` - POST to send email
- [ ] All responses return proper JSON structure
- [ ] Error responses include error message
- [ ] Authentication via Bearer token (if token configured)
- [ ] Proper HTTP status codes (200, 400, 404, etc.)
