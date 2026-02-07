# Timeout Fix - Handling Long-Running Email Queries

## Problem

When asking complex queries like "Give me an overview of all the movies that I went to see last week at the Pathe", you get:

```
Maximum execution time of 60 seconds exceeded
```

## Root Cause

The default PHP execution timeout (60 seconds) is too short when:

1. **Multiple email searches**: AI searches for "pathe", "cinema", "movie", etc.
2. **Multiple email reads**: For each search result, AI reads the full email content
3. **Information extraction**: Each email extraction makes additional API calls
4. **Sequential API calls**: Each HTTP request takes time to:
   - Connect to the server
   - Send request
   - Wait for response
   - Process result

**Example Timeline**:
```
Search "pathe" → 3 emails found (1-2 sec)
Read email 1 → 2-3 sec
Read email 2 → 2-3 sec
Read email 3 → 2-3 sec
Extract info from email 1 → 1-2 sec
Extract info from email 2 → 1-2 sec
Extract info from email 3 → 1-2 sec
OpenAI API call → 5-10 sec
---
Total: 20-30+ seconds minimum
With multiple searches: 40-60+ seconds
```

## Solution Implemented

### 1. Increased PHP Execution Timeout

**File**: `app/Http/Controllers/ChatController.php`

```php
set_time_limit(300); // 5 minutes
```

**What it does**: Allows the entire request to run for up to 5 minutes instead of the default 60 seconds.

**When it applies**: Every time a message is sent to the AI.

### 2. HTTP Request Timeouts

**File**: `app/AI/Services/AIService.php`

```php
->timeout(60) // 60 seconds per OpenAI API call
```

**What it does**:
- Prevents individual API calls to OpenAI from hanging indefinitely
- Raises an exception if request takes longer than 60 seconds
- Allows graceful handling instead of silent hangs

### 3. Email API Timeouts

**File**: `app/AI/Plugins/ApiBasedPlugin.php`

```php
->timeout(30) // 30 seconds per email API call
```

**What it does**:
- Prevents email searches/reads from hanging
- Limits each email API call (search, read, extract) to 30 seconds
- Allows the AI to handle slow API responses gracefully

## Timeout Hierarchy

```
Request Timeout
├─ PHP Execution: 300 seconds (5 minutes)
│
├─ OpenAI API: 60 seconds
│   └─ May happen multiple times during conversation
│
└─ Email API: 30 seconds
    └─ Happens multiple times per query (search, read, extract)
```

## How It Works Now

**Complex Query Example**:

```
User: "Show me all movies I went to see last week at Pathe"

Timeline (within 5-minute limit):
├─ Search "pathe" + "cinema" + "movie" (3 searches x 2 sec = 6 sec)
├─ Read email 1 full content (2 sec)
├─ Read email 2 full content (2 sec)
├─ Read email 3 full content (2 sec)
├─ Extract movie info from email 1-3 (3 x 2 sec = 6 sec)
├─ Verify extracted data matches query (1 sec)
├─ OpenAI generates overview response (10 sec)
│
Total: ~30 seconds (well within 5-minute limit)
```

## Benefits

✅ **No More Timeout Errors**: Complex queries with multiple emails work
✅ **Graceful Degradation**: If one API call is slow, others can continue
✅ **Prevents Infinite Hangs**: Each API call has a timeout
✅ **Sufficient Time**: 5 minutes allows for:
- Multiple search attempts
- Reading many emails
- Extracting structured data
- Processing through AI

## Limitations

⚠️ **Still Sequential**: API calls happen one after another, not in parallel
- Could be optimized in future with concurrent requests
- For now, acceptable for typical use cases

## Configuration

All timeouts are hardcoded in the application:
- PHP execution: 300 seconds (ChatController.php line 38)
- OpenAI API: 60 seconds (AIService.php lines 60, 163)
- Email API: 30 seconds (ApiBasedPlugin.php line 44)

To change these values, edit the respective files.

## Testing

Test with complex queries:
- "Show me all movies I saw at Pathe last week"
- "Give me an overview of my recent orders"
- "List all my holiday bookings from the past year"

These should now complete without timeout errors.

## Related Improvements

These timeouts work together with:
- Multiple search strategies (try different keywords)
- Email verification (verify each email matches query)
- Recursive tool calling (retry with different approaches)
- Information extraction (get structured data from emails)
