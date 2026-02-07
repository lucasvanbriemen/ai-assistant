# Email Webhook - Incoming Email Processing

This webhook allows you to send incoming emails from your email service to the AI agent, which will automatically process them and take actions (like creating calendar events).

## Endpoint

**POST** `/api/emails/incoming`

## How It Works

1. Email arrives in your email service
2. Your email service POSTs the email data to this webhook
3. The AI analyzes the email content
4. The AI automatically takes actions:
   - Creates calendar events for meetings/appointments
   - Extracts dates, times, and actionable items
   - Suggests responses or next steps
5. Response includes what actions were taken

## Request Format

### Required Fields

```json
{
  "id": "unique_email_id_from_your_system",
  "subject": "Email Subject Line",
  "sender": "sender@example.com",
  "date": "2026-02-07T10:30:00Z",
  "body": "Full email body content here..."
}
```

### Field Descriptions

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | string | Yes | Unique identifier for the email in your system |
| `subject` | string | Yes | Email subject line |
| `sender` | string | Yes | Sender's email address (must be valid email format) |
| `date` | string | Yes | Email date (ISO 8601 format recommended, e.g., `2026-02-07T10:30:00Z`) |
| `body` | string | Yes | Full email body content (plain text or HTML) |

## Example Requests

### Example 1: Team Lunch Email

```bash
curl -X POST http://localhost/api/emails/incoming \
  -H "Content-Type: application/json" \
  -d '{
    "id": "email_123456",
    "subject": "Team Lunch This Friday",
    "sender": "hr@company.com",
    "date": "2026-02-05T09:00:00Z",
    "body": "Hi Everyone,\n\nWe are organizing a team lunch this Friday, February 14th at noon at the Italian restaurant downtown. Please RSVP with your dietary preferences.\n\nLooking forward to seeing you there!"
  }'
```

**AI Response:**
```json
{
  "success": true,
  "email_id": "email_123456",
  "subject": "Team Lunch This Friday",
  "sender": "hr@company.com",
  "ai_analysis": "I've analyzed the incoming email about a team lunch. The email mentions a team lunch scheduled for Friday, February 14th at noon at the Italian restaurant downtown. I've automatically created a calendar event with the following details:\n\n- Title: Team Lunch\n- Date: Friday, February 14, 2026\n- Time: 12:00 PM\n- Location: Downtown Italian Restaurant\n\nThe event has been added to your calendar. You may want to RSVP to the original email with your dietary preferences.",
  "tools_used": [
    {
      "name": "create_event",
      "parameters": {
        "title": "Team Lunch",
        "description": "Team lunch at Italian restaurant downtown",
        "start_time": "2026-02-14 12:00",
        "end_time": "2026-02-14 13:30",
        "location": "Downtown Italian Restaurant"
      }
    }
  ]
}
```

### Example 2: Meeting Request Email

```bash
curl -X POST http://localhost/api/emails/incoming \
  -H "Content-Type: application/json" \
  -d '{
    "id": "email_789012",
    "subject": "Meeting: Q1 Planning - Tomorrow 2 PM",
    "sender": "manager@company.com",
    "date": "2026-02-06T14:30:00Z",
    "body": "Hi,\n\nI would like to schedule a Q1 planning meeting for tomorrow (February 7th) at 2:00 PM in Conference Room B. The meeting should take about 1.5 hours.\n\nPlease confirm your attendance.\n\nThanks"
  }'
```

**AI Response:**
```json
{
  "success": true,
  "email_id": "email_789012",
  "subject": "Meeting: Q1 Planning - Tomorrow 2 PM",
  "sender": "manager@company.com",
  "ai_analysis": "I've processed the meeting request email. I've automatically created a calendar event for the Q1 Planning meeting:\n\n- Title: Q1 Planning Meeting\n- Date: Friday, February 7, 2026 (tomorrow)\n- Time: 2:00 PM - 3:30 PM\n- Location: Conference Room B\n\nThe event is now on your calendar. You may want to confirm your attendance by replying to the original email.",
  "tools_used": [
    {
      "name": "create_event",
      "parameters": {
        "title": "Q1 Planning Meeting",
        "start_time": "2026-02-07 14:00",
        "end_time": "2026-02-07 15:30",
        "location": "Conference Room B",
        "description": "Q1 Planning meeting"
      }
    }
  ]
}
```

## Response Format

### Success Response (HTTP 200)

```json
{
  "success": true,
  "email_id": "email_123456",
  "subject": "Email Subject",
  "sender": "sender@example.com",
  "ai_analysis": "AI's analysis and actions taken...",
  "tools_used": [
    {
      "name": "tool_name",
      "parameters": { /* parameters used */ }
    }
  ]
}
```

### Error Responses

**Validation Error (HTTP 422)**
```json
{
  "success": false,
  "error": "Validation failed",
  "errors": {
    "subject": ["The subject field is required."],
    "sender": ["The sender field must be a valid email address."]
  }
}
```

**Processing Error (HTTP 500)**
```json
{
  "success": false,
  "error": "Failed to process email: Error message here",
  "email_id": "email_123456"
}
```

## Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `success` | boolean | Whether processing was successful |
| `email_id` | string | The ID of the processed email |
| `subject` | string | Email subject (echo from request) |
| `sender` | string | Sender email (echo from request) |
| `ai_analysis` | string | AI's analysis and actions taken |
| `tools_used` | array | List of tools/actions the AI executed |

## What the AI Does

The AI will automatically analyze incoming emails and:

1. **Extract Information**: Identify dates, times, locations, and participants
2. **Create Calendar Events**: For meetings, appointments, events mentioned in the email
3. **Extract Deadlines**: Identify and alert about project deadlines
4. **Suggest Actions**: Recommend responses or follow-ups needed
5. **Summarize**: Provide a human-readable summary of the email and actions taken

## Integration Example

Here's how you might integrate this in your email service (Node.js example):

```javascript
// When an email arrives in your email service
app.post('/email-received', async (req, res) => {
  const { id, subject, from, date, body } = req.body;

  try {
    // Forward to AI agent webhook
    const response = await fetch('http://localhost/api/emails/incoming', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        id,
        subject,
        sender: from,
        date,
        body
      })
    });

    const result = await response.json();

    if (result.success) {
      console.log('Email processed:', result.ai_analysis);
      console.log('Actions taken:', result.tools_used);
    } else {
      console.error('Processing failed:', result.error);
    }

  } catch (error) {
    console.error('Webhook error:', error);
  }
});
```

## Notes

- The webhook processes emails asynchronously in the AI system
- The AI may take a few seconds to analyze and process the email
- Make sure your email service can handle the JSON response (you don't need to do anything with it, it's informational)
- The AI uses the Calendar plugin to create events, so make sure it's enabled and configured properly
- Include as much detail in the email body as possible for better AI analysis

## Testing the Webhook

You can test the webhook locally with curl:

```bash
curl -X POST http://localhost:8000/api/emails/incoming \
  -H "Content-Type: application/json" \
  -d '{
    "id": "test_001",
    "subject": "Test Email - Team Meeting",
    "sender": "test@example.com",
    "date": "2026-02-07T10:00:00Z",
    "body": "Meeting scheduled for tomorrow at 2 PM in the conference room."
  }'
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| 422 Validation Error | Check that all required fields are present and email addresses are valid |
| 500 Processing Error | Check AI service logs, ensure calendar plugin is enabled |
| Calendar events not created | Verify Calendar plugin is configured and enabled in `.env` |
| AI not taking expected actions | Include more detail in email body (dates, times, locations) |

