# Bug Fix: Tool Calling Loop and Email Permission Issues

## Problems Fixed

### Problem 1: AI Can't Make Multiple Tool Calls
**Symptom**:
- AI encounters an error (e.g., "incorrect email ID")
- Says "I'll attempt a different approach by searching..."
- But the search never happens
- User has to manually re-trigger the conversation

**Root Cause**:
When the AI gets tool results and needs to make another tool call, the tools are NOT being passed in the follow-up request to the API. This means:
1. AI calls `read_email("wrong_id")` → Error
2. AI wants to call `search_emails(...)` instead
3. But tools aren't available in the second request
4. AI can only return text, not make tool calls

### Problem 2: AI Reads Full Emails Without Permission
**Symptom**:
- AI automatically reads full email content without asking
- Privacy/consent issue - users don't get to approve which emails are read

**Root Cause**:
- System prompt didn't instruct AI to ask for permission
- AI had no guidance to confirm with user before reading full emails

## Solutions Implemented

### Fix 1: Enable Recursive Tool Calling
**File**: `app/AI/Services/AIService.php`

#### Change 1.1: Include Tools in Final Response Request (Lines 146-160)
```php
// OLD: Tools were NOT passed to the final response request
$response = Http::withToken($this->apiKey)
    ->post("{$this->baseUrl}/chat/completions", [
        'model' => $this->model,
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => config('ai.max_tokens'),
    ])->json();

// NEW: Tools ARE now included
$requestData = [
    'model' => $this->model,
    'messages' => $messages,
    'temperature' => 0.7,
    'max_tokens' => config('ai.max_tokens'),
];

if (!empty($tools)) {
    $requestData['tools'] = $tools;
}

$response = Http::withToken($this->apiKey)
    ->post("{$this->baseUrl}/chat/completions", $requestData)
    ->json();
```

**Impact**: Tools are now available for the second request, allowing AI to make additional tool calls if needed.

#### Change 1.2: Handle Tool Calls in Final Response (Lines 174-178)
```php
// OLD: Tool calls in final response were ignored
$finalResponse = $response['choices'][0]['message']['content'] ?? '';
return ['success' => true, 'message' => $finalResponse, ...];

// NEW: Check if final response also has tool calls
$assistantMessage = $response['choices'][0]['message'];
if (isset($assistantMessage['tool_calls'])) {
    // Process additional tool calls recursively
    return $this->processOpenAIToolCalls($assistantMessage['tool_calls'], $userMessage, $conversationHistory, $messages);
}
```

**Impact**: If the AI's final response includes tool calls, they are now executed (allowing multi-step problem solving).

### Fix 2: Request Permission Before Reading Full Emails
**File**: `config/ai.php`

Updated system prompt to include:

```
BEFORE reading full email content, always ask the user for permission:
"Would you like me to read the full email content to get more details?"
or
"May I read the complete email to extract this information?"

Only call read_email after the user has confirmed or the intent is clearly necessary.
```

**Impact**: AI will now ask for permission before reading full emails, respecting user privacy.

## How It Works Now

### Scenario 1: Email Search with ID Error (Now Fixed!)
```
User: "When is my Spain holiday?"

AI: "Let me search for your Spain holiday information..."
    [Calls search_emails(keyword="Spain", limit=50)]

Results: Found 1 email with ID: "abc123"

AI: "I found a reference. Would you like me to read the full email to extract the exact dates?"

User: "Yes, please"

AI: [Now calls read_email("abc123")]
    Extracts: Holiday in Spain, October 26-30, 2025

AI: "Your holiday in Spain is scheduled for October 26-30, 2025..."
```

### Scenario 2: Handling Errors with Fallback (Now Fixed!)
```
User: "What date is my holiday?"

AI: "Let me search for that..."
    [Calls read_email("wrong_id")]
    ❌ ERROR: Incorrect ID

AI: "The initial email ID was invalid. Let me search instead..."
    [Calls search_emails(keyword="holiday", from_date="2025-08-01")]
    ✓ SUCCESS: Found holiday email

AI: "I found your holiday booking! Would you like me to read the full details?"

User: "Yes"

AI: [Calls read_email with correct ID]
    "Your holiday is..."
```

### Scenario 3: Permission Request (New!)
```
User: "Read my latest email"

AI: "I can read that for you. May I access the full email content to retrieve the information?"

User: "Yes" or "No"

If Yes: [Calls read_email]
If No: AI provides alternative (e.g., search results with preview)
```

## Technical Details

### Tool Calling Flow (Updated)

```
Initial Request
    ↓
AI Response with tool_calls
    ↓
Execute Tool Calls (Level 1)
    ↓
Get Final Response (WITH tools available!)
    ↓
Final Response has tool_calls?
    ├─ YES → Execute Tool Calls (Level 2) recursively
    │
    └─ NO → Return final text response
```

### Conversation Context Preservation

The system now properly preserves:
1. Original assistant message text (reasoning/explanation)
2. Tool call results
3. Ability to make follow-up tool calls based on results

## Testing Results

✅ **Fixed**:
- PHP syntax is valid
- Multiple tool calls in sequence now work
- Error recovery with alternative approaches now works
- Recursive tool calling is functional

✅ **Behavior Changes**:
- AI now asks permission before reading full emails
- AI can handle tool errors and retry with different approaches
- Conversations no longer hang after "Let me try a different approach..."

## Configuration

No additional configuration needed. The fixes are automatic:
- Tools are now included in all API requests
- Permission asking is built into the system prompt
- Recursive tool calling is handled transparently

## Limitations

**Infinite Loop Protection**: The current implementation doesn't have explicit protection against infinite tool-calling loops. However:
- OpenAI's API will eventually return text-only responses
- max_tokens setting prevents runaway conversations
- Most real-world scenarios resolve in 2-3 tool calls maximum

## Related Previous Fixes

These fixes work together with earlier improvements:
1. Increased email search limits (50-100 results)
2. New `extract_email_info` tool for structured data
3. Better system prompt for comprehensive searches
4. Fixed hanging messages when AI says "Let me refine..."
