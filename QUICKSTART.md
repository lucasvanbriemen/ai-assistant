# AI Chatbot - Quick Start Guide

## 1. Setup (5 minutes)

### Option A: OpenAI
```bash
# Edit .env and add:
AI_PROVIDER=openai
OPENAI_API_KEY=sk-your-key-here
OPENAI_MODEL=gpt-4-turbo-preview
```

### Option B: Anthropic
```bash
# Edit .env and add:
AI_PROVIDER=anthropic
ANTHROPIC_API_KEY=sk-ant-your-key-here
ANTHROPIC_MODEL=claude-3-5-sonnet-20241022
```

## 2. Run (2 terminals)

```bash
# Terminal 1
php artisan serve

# Terminal 2
npm run dev
```

## 3. Access

Open `http://localhost:8000/chat`

## 4. Try It!

Ask questions like:
- "Show me my unread emails"
- "When did I get a confirmation mail for my holiday?"
- "Create a calendar event for the cinema on Friday at 7pm"
- "What events do I have next week?"

---

## That's It! 🎉

The chatbot works with mock data immediately. Real APIs can be added later without changing core code.

### Want More?

- **Setup Guide**: `AI_CHATBOT_SETUP.md`
- **Full Summary**: `IMPLEMENTATION_SUMMARY.md`
- **What Was Built**: `FILES_CREATED.md`

### Key Endpoints

```
POST /api/chat/send          # Send message
GET /api/chat/tools          # Get available tools
```

### Architecture

```
User → ChatBot UI → API → AIService → Plugins → Response
```

### Add New Plugin

1. Create class implementing `PluginInterface`
2. Register in `app/Providers/AIServiceProvider.php`
3. Done! Tools are available to AI

### Replace Mock Data

Just modify plugin methods - no core changes needed:

```php
// Before: Mock
return ToolResult::success($mockData);

// After: Real API
$results = $this->gmail->search(...);
return ToolResult::success($results);
```

---

**Status**: ✅ Complete and ready to use

**Next**: Add your API key and start chatting!
