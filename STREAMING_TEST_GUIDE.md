# Streaming Implementation - Testing Guide

## Quick Start

### 1. Enable Streaming in `.env`
```
AI_STREAMING_ENABLED=true
AI_STREAMING_TIMEOUT=60
```

### 2. Manual Backend Test with curl

**Test basic streaming:**
```bash
curl -X POST http://localhost:8000/api/chat/stream \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: $(grep -oP 'csrf-token" content="\K[^"]+' index.html)" \
  -d '{"message":"What is 2+2?", "history":[]}' \
  --no-buffer
```

**Expected output (SSE format):**
```
event: chunk
data: {"content":"2"}

event: chunk
data: {"content":"+"}

event: chunk
data: {"content":"2"}

event: chunk
data: {"content":"="}

event: chunk
data: {"content":"4"}

event: done
data: {"message":"2+2=4"}
```

**Test with longer response:**
```bash
curl -X POST http://localhost:8000/api/chat/stream \
  -H "Content-Type: application/json" \
  -d '{"message":"Tell me a short joke", "history":[]}' \
  --no-buffer
```

### 3. Frontend UI Testing

#### Test 1: Basic Streaming
1. Open chat app in browser
2. Send message: "What is the capital of France?"
3. **Verify:**
   - Text appears progressively (word by word)
   - Button shows "Streaming..." during response
   - Textarea is disabled
   - Response completes and button returns to "Send"

#### Test 2: Fallback on Error
1. Open DevTools (F12) → Network tab
2. Send message: "Hello"
3. Before response completes, set Network tab to "Offline"
4. **Verify:**
   - Streaming fails
   - Frontend automatically uses sync endpoint
   - Message still appears in UI
   - Console shows fallback message

#### Test 3: Multiple Messages
1. Send first message: "What is 2+2?"
2. Immediately try sending second message before first completes
3. **Verify:**
   - Second button click ignored (disabled during streaming)
   - First message completes normally
   - Can send second message only after first finishes

#### Test 4: Long Response
1. Send message: "Write a detailed explanation of photosynthesis"
2. **Verify:**
   - Text stream smoothly
   - No loading delay before seeing response
   - Button transitions correctly when done

### 4. Console Debugging

**In browser DevTools Console:**

```javascript
// Check if StreamHandler is available
console.log(typeof StreamHandler) // Should be 'function'

// Monitor streaming calls
api.stream('/api/chat/stream',
  {message: 'test', history: []},
  (chunk) => console.log('Chunk:', chunk),
  (final) => console.log('Complete:', final),
  (error) => console.error('Error:', error)
)
```

### 5. Network Inspector

**In DevTools Network tab:**

1. Filter by Fetch requests
2. Look for `/api/chat/stream` request
3. Check **Type:** Should be `fetch`
4. Check **Response tab:** Should show SSE format chunks
5. Check **Headers:**
   - Request: `Content-Type: application/json`
   - Response: `Content-Type: text/event-stream`

### 6. Performance Monitoring

**Measure streaming performance:**

```javascript
// In browser console
const start = performance.now();

api.stream('/api/chat/stream',
  {message: 'Tell me a joke', history: []},
  () => {},
  () => {
    const duration = performance.now() - start;
    console.log(`Streaming completed in ${duration}ms`);
  },
  (error) => console.error(error)
);
```

## Comprehensive Test Cases

### Test Case 1: Simple Response
**Input:** "What is 2+2?"
**Expected:** Quick response, text streams progressively
**Verify:** No errors in console, button works correctly

### Test Case 2: Longer Response
**Input:** "Write a haiku about programming"
**Expected:** Longer response streamed smoothly
**Verify:** Message history saved correctly

### Test Case 3: Special Characters
**Input:** "Say 'hello' with emojis 👋"
**Expected:** Special chars and emojis render correctly
**Verify:** No encoding issues

### Test Case 4: Empty Message
**Input:** (send empty/whitespace)
**Expected:** Button disabled, no request sent
**Verify:** Works as before, no regression

### Test Case 5: Rapid Sends
**Action:** Type message, send, immediately type another, send
**Expected:** Second send waits for first to complete
**Verify:** Button prevented during streaming

### Test Case 6: Long Conversation History
**Setup:** 10+ messages in history
**Input:** Send new message
**Expected:** Streaming works with large history
**Verify:** API properly filters and sends history

### Test Case 7: Network Interruption
**Setup:** Start streaming
**Action:** Disable network during response
**Expected:** Falls back to sync, completes response
**Verify:** Console shows fallback message

### Test Case 8: Timeout Scenario
**Setup:** `AI_STREAMING_TIMEOUT=5` in .env
**Input:** Message that takes >5 seconds
**Expected:** Streaming fails gracefully, falls back
**Verify:** No browser hang, error handling works

### Test Case 9: Browser Compatibility
**Test in:** Chrome, Firefox, Safari, Edge
**Input:** "Hello"
**Expected:** Works identically in all browsers
**Verify:** Progressive text rendering works

### Test Case 10: Mobile Testing
**Device:** iPhone/Android
**Input:** "Tell me a story"
**Expected:** Streaming works on mobile
**Verify:** UI responsive, text renders correctly

## Rollback Testing

### If Issues Occur

1. **Disable streaming (immediate):**
   ```bash
   # .env
   AI_STREAMING_ENABLED=false
   ```
   - Application falls back to sync endpoint
   - No code changes needed

2. **Revert commits (if needed):**
   ```bash
   git revert <commit-hash>
   ```
   - Removes all streaming code
   - Restores original behavior

## Performance Baseline

### Metrics to Measure

**Before Streaming:**
- Time to first response: ~2-3 seconds
- Time to complete response: ~4-6 seconds
- User must wait for entire response

**After Streaming:**
- Time to first byte: ~100-300ms (improvement)
- User sees response start immediately
- Full response still takes similar time total

### Success Criteria

- First byte latency < 500ms ✓
- Text renders smoothly without pause ✓
- Fallback works on network error ✓
- No increase in error rates ✓
- All test cases pass ✓

## Debugging Tips

### If Streaming Not Working

1. **Check configuration:**
   ```bash
   php artisan tinker
   >>> config('ai.streaming')
   # Should return array with 'enabled' => true
   ```

2. **Check API key:**
   ```bash
   php artisan tinker
   >>> config('ai.openai.api_key')
   # Should return your OpenAI API key (not empty/null)
   ```

3. **Check routes:**
   ```bash
   php artisan route:list | grep stream
   # Should show POST /api/chat/stream route
   ```

4. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log
   # Watch for streaming errors
   ```

### If Fallback Not Working

1. **Enable fallback:**
   ```bash
   # .env
   AI_STREAMING_FALLBACK=true
   ```

2. **Check sync endpoint:**
   ```bash
   curl -X POST http://localhost:8000/api/chat/send \
     -H "Content-Type: application/json" \
     -d '{"message":"test", "history":[]}'
   ```

3. **Verify error handling in frontend:**
   - Open DevTools console
   - Look for "Streaming failed, falling back to sync:" message
   - Check that sync request is made after

## Success Checklist

Before considering streaming implementation complete:

- [ ] Streaming endpoint works with curl
- [ ] UI shows progressive text rendering
- [ ] Button shows "Streaming..." state
- [ ] Textarea disabled during streaming
- [ ] Multiple rapid sends are prevented
- [ ] Fallback triggered on network error
- [ ] Fallback also works (message appears)
- [ ] Works in Chrome, Firefox, Safari
- [ ] Works on mobile devices
- [ ] No errors in console
- [ ] No errors in server logs
- [ ] Performance improvement measurable
- [ ] All test cases pass

## Issues & Troubleshooting

### Issue: Streaming endpoint returns 404
**Solution:** Check that route is registered in `routes/api.php`
```bash
php artisan route:list | grep stream
```

### Issue: No text appears progressively
**Solution:** Check browser compatibility (need modern fetch API)
```javascript
// In console
console.log(typeof fetch); // Should be 'function'
console.log('ReadableStream' in window); // Should be true
```

### Issue: "Streaming failed" constantly
**Solution:** Check OpenAI API key and network connectivity
```bash
curl https://api.openai.com/v1/models \
  -H "Authorization: Bearer $OPENAI_API_KEY"
```

### Issue: Button stuck on "Streaming..."
**Solution:** Check browser console for JavaScript errors
- Could indicate unhandled exception
- Try refreshing page

### Issue: Fallback not completing
**Solution:** Check that sync endpoint works
```bash
curl -X POST http://localhost:8000/api/chat/send \
  -H "Content-Type: application/json" \
  -d '{"message":"test", "history":[]}'
```

## Final Verification

Run this complete test before marking as done:

```bash
# 1. Check configuration
php artisan tinker
config('ai.streaming')

# 2. Test streaming endpoint
curl -X POST http://localhost:8000/api/chat/stream \
  -H "Content-Type: application/json" \
  -d '{"message":"Hello", "history":[]}' \
  --no-buffer

# 3. Open app in browser
# 4. Send message and verify streaming UI
# 5. Disable network and verify fallback
# 6. All tests pass ✓
```
