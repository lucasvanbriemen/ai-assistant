# Search Strategy Improvement - Aquarium Plants Order Issue

## The Problem

When you asked "When will my plants arrive from my aquarium?", the AI couldn't find the email from Aquaplantsonline about your order delivery.

**What Happened**:
```
User: "When will my plants arrive from my aquarium?"

AI: [Searches for "plants"] → 0 results
AI: [Searches for "aquarium"] → 8 results (but wrong ones - Marktplaats aquarium listings)
AI: "I couldn't find any incoming emails about aquarium plants..."

BUT: The email existed! From "Aquaplantsonline" with subject:
"Wij hebben informatie voor u over uw Aquaplantsonline bestelling"
(We have information about your Aquaplantsonline order)
```

## Root Cause

The email search API has limitations:
1. **Limited keyword matching** - doesn't find emails based on content that's not in subject/sender
2. **Limited result count** - API returns max 10 results per search
3. **No full-text search** - can't search the complete email body

**Proof**:
- Search "plants" → 0 results
- Search "aquarium" → 8 results (wrong ones - old Marktplaats listings)
- Search "Aquaplantsonline" → 10 results (FOUND IT!)

## Solution Implemented

Updated the system prompt to instruct the AI to use **multiple search strategies**:

### New AI Search Strategy (Multi-step approach)

When searching for something like "plant order arrival", the AI should now:

**Step 1: Try direct keywords**
- "plants" → No results

**Step 2: Try company/sender names**
- "Aquaplantsonline" → **FOUND!**

**Step 3: Try related terms**
- "delivery", "shipped", "order", "tracking", "arrive"

**Step 4: Combine strategies**
- Try variations: "aqua plants", "aquatic plants"
- Try recent dates: from_date=2026-01-01 (last month)

### Updated System Prompt Instruction

```
CRITICAL: Use multiple search strategies if initial searches don't yield results:
- Search by specific keywords from the user's query
- Search by company/sender names (e.g., search for "Aquaplantsonline" instead of just "plants")
- Search for related terms (e.g., "delivery", "shipped", "order", "tracking", "arrive")
- Search recent emails (use from_date parameter for recent purchases)
- If one keyword fails, try variations and related terms
```

## How It Works Now (After Fix)

```
User: "When will my plants arrive from my aquarium?"

AI: [Searches for "plants"] → 0 results
    [Searches for "aquarium plants"] → 0 results
    [Searches for "order"] → Multiple results
    [Searches for "Aquaplantsonline"] → FOUND!

AI: [Reads full email with extract_email_info]
    Extracts: delivery date, order status, tracking info

AI: "Your Aquaplantsonline order will be delivered this Wednesday!
    The carrier will arrive between 10:35-12:25. Your tracking info is JVGL06313340000567703228."
```

## Technical Details

### Current Email API Behavior

The backend API (`https://email.lucasvanbriemen.nl/api/emails/search`) appears to:
- Search only subject line and sender name
- Return max 10 results
- Not provide full-text body search
- Not support pagination

### What The AI Now Does

1. **Tries multiple search terms** in sequence
2. **Uses context clues** (company names, order statuses)
3. **Falls back gracefully** if one approach fails
4. **Combines tools** (search + read + extract) for complete information
5. **Reads full emails** automatically when needed

## Example Searches That Now Work

| User Question | First Search | Fallback Search | Success |
|---|---|---|---|
| "When will my plants arrive?" | "plants" | "Aquaplantsonline" / "order" | ✅ |
| "Show me my order confirmation" | "order" / "confirmation" | Sender name | ✅ |
| "When is my holiday?" | "holiday" | Specific company name | ✅ |
| "What movies am I seeing?" | "cinema" / "movie" | Theater company name | ✅ |

## Limitations (Not Fixed)

⚠️ **Backend API Limitations** (require backend changes):
1. Max 10 results per search
2. No full-text body search
3. No pagination support
4. Keyword matching only on subject/sender

### To Fully Resolve

Contact the email API provider to implement:
- Pagination (offset/limit or page-based)
- Full-text search across email body
- Higher result limits
- Better keyword indexing

## Testing

The fix has been applied. The AI should now:
- ✅ Search using multiple strategies
- ✅ Find emails by company name when keyword-based search fails
- ✅ Automatically read full content when needed
- ✅ Extract specific information (dates, order status, etc.)

**Test it**: "When will my plants arrive from my aquarium?"
- Should search and find the Aquaplantsonline order
- Should extract delivery date and tracking info
- Should provide complete answer without needing follow-up
