# Email Client Fixes - Summary

## Issues Addressed

### Issue 1: Limited Email Results (Only 10 Emails)
**Problem**: The search was limited to returning only 10 emails, preventing discovery of older emails like holiday bookings from last month.

**Solution**:
- Removed hardcoded limit of 10 in `searchEmails()` method
- Added `limit` parameter to the `search_emails` tool definition
- Default limit is now 50 results, with a maximum of 100 results
- The AI can request more emails when needed by specifying a higher limit

**Changes**:
- `app/AI/Plugins/EmailPlugin.php`:
  - Added `limit` parameter to search_emails tool definition (lines 83-86)
  - Updated `searchEmails()` to respect limit parameter (lines 205-207)
  - Updated `searchEmailsViaApi()` to pass limit parameter to API (lines 218-228)

### Issue 2: Difficulty Extracting Information from Emails
**Problem**: The AI had trouble extracting specific information (like movie titles) from emails because:
1. Search results only show 100-200 character previews
2. Complex HTML email formats make extraction difficult
3. AI wasn't automatically reading full emails when needed

**Solutions**:

#### A. Improved Email Previews
- Increased preview size from 100 to 200 characters in search results
- Gives better context without requiring full email reads

#### B. New `extract_email_info` Tool
- Added a specialized tool for extracting structured information from emails
- Supports multiple field types with smart pattern matching
- Handles common email fields: movie_title, date, time, location, seat, confirmation_number, etc.
- AI can now precisely extract information without parsing complex HTML

**Example Usage**:
```
User: "Which movies am I going to next month?"
AI:
  1. Searches for cinema/movie emails with limit of 50
  2. Gets results with 200-char previews
  3. For each relevant email, calls extract_email_info with fields: ["movie_title", "date", "time", "location"]
  4. Returns comprehensive movie information
```

**Supported Fields for Extraction**:
- `movie_title`, `film_title` - Movie/film titles
- `date`, `event_date` - Event dates
- `time`, `event_time` - Event times
- `location`, `venue` - Locations/venues
- `seat`, `seats` - Seat information
- `confirmation_number`, `booking_number`, `reservation_number` - Booking details
- Custom field names (uses pattern matching)

#### C. Updated System Prompt
- Enhanced AI instructions to:
  - Search with appropriate limits (up to 100 results)
  - Use `read_email` for full content when needed
  - Use `extract_email_info` for structured data extraction
  - Search thoroughly and not give up easily

**Changes**:
- `app/AI/Plugins/EmailPlugin.php`:
  - Added `extract_email_info` tool definition (lines 143-160)
  - Added `extractEmailInfo()` method (lines 269-299)
  - Added `extractField()` helper method with smart pattern matching (lines 301-372)
  - Updated `executeTool()` to handle new tool (line 156)
  - Increased preview from 100 to 200 characters (line 206)

- `config/ai.php`:
  - Updated system prompt with detailed guidelines for email searches
  - Added instructions about using extract_email_info tool

- `API_CONTRACTS.md`:
  - Documented limit parameter for search endpoint
  - Added documentation for extract_email_info tool

## Technical Implementation

### Tool Definition (EmailPlugin.php)
```php
new ToolDefinition(
    name: 'extract_email_info',
    description: 'Extract specific information from an email body',
    parameters: [
        'type' => 'object',
        'properties' => [
            'email_id' => ['type' => 'string', ...],
            'fields' => ['type' => 'array', ...],
        ],
        'required' => ['email_id', 'fields'],
    ],
    category: 'extract'
)
```

### Smart Field Extraction
The `extractField()` method uses regex patterns to intelligently find:
- Movie titles in various email formats
- Dates in multiple formats
- Times with AM/PM support
- Location/venue information
- Seat/row assignments
- Confirmation/booking numbers

## Testing

All changes have been validated:
- ✅ PHP syntax is valid
- ✅ No compilation errors
- ✅ API contracts documented
- ✅ Backward compatible (existing code still works)

## Future Improvements

1. **Backend API Pagination**: When the email API supports pagination parameters (limit, offset, page), the code will automatically use them.

2. **Enhanced Field Extraction**: The pattern matching can be extended with:
   - Natural language processing for better field detection
   - Machine learning for complex email parsing
   - HTML to text conversion for better email parsing

3. **Email Caching**: Implement caching to avoid re-fetching the same emails

4. **Batch Operations**: Allow extracting information from multiple emails in one call

## User Impact

Users can now:
1. **Find older emails**: Ask about events from any time period, not just recent ones
2. **Extract specific information**: Ask "What movies am I going to?" and get movie titles, dates, and times
3. **Handle complex emails**: The AI can now understand HTML-formatted emails better
4. **Get comprehensive results**: Searches return up to 100 emails instead of just 10
