# AI Chatbot Implementation Guide

## Overview

You've successfully implemented a fully modular AI chatbot system with pluggable integrations. This system allows the AI to interact with your real data sources (email, calendar, etc.) through natural language.

## What Was Built

### Backend (Laravel/PHP)

#### 1. **Plugin System** (`app/AI/`)
- **Contracts**: Define how plugins work
  - `PluginInterface.php` - Contract all plugins must implement
  - `ToolDefinition.php` - Describes tools AI can call
  - `ToolResult.php` - Wrapper for tool execution results

- **Core**: Plugin management
  - `PluginRegistry.php` - Discovers and manages plugins, routes tool calls
  - `ParameterValidator.php` - Validates tool parameters against JSON Schema

- **Plugins**: Concrete integrations
  - `EmailPlugin.php` - Mock email integration with 4 tools
  - `CalendarPlugin.php` - Mock calendar integration with 4 tools

- **Services**: AI orchestration
  - `AIService.php` - Handles both OpenAI and Anthropic APIs

#### 2. **API Layer** (`app/Http/Controllers/`)
- `ChatController.php` - Endpoints for chat and tool discovery

#### 3. **Configuration**
- `config/ai.php` - AI provider configuration
- `app/Providers/AIServiceProvider.php` - Service registration
- `bootstrap/providers.php` - Provider registration

### Frontend (Svelte 5)

#### 1. **Components**
- `resources/js/components/ChatBot.svelte` - Full-featured chat UI
  - Message history with timestamps
  - Example queries for users
  - Tool usage badges
  - Loading states and error handling

#### 2. **Pages**
- `resources/js/pages/Chat.svelte` - Chat page that uses ChatBot component

#### 3. **Routes**
- Added `/chat` route in `resources/js/stores/routes.svelte.js`
- Added navigation link on home page

## Getting Started

### 1. Set Up Environment

Copy the AI configuration to your `.env`:

```bash
# Add to .env
AI_PROVIDER=openai
OPENAI_API_KEY=your_api_key_here
OPENAI_MODEL=gpt-4-turbo-preview

# Or use Anthropic:
# AI_PROVIDER=anthropic
# ANTHROPIC_API_KEY=your_api_key_here
# ANTHROPIC_MODEL=claude-3-5-sonnet-20241022

AI_MAX_HISTORY=20
AI_MAX_TOKENS=2048
```

### 2. Start Development Server

```bash
# Terminal 1: Start Laravel
php artisan serve

# Terminal 2: Start Vite
npm run dev
```

### 3. Access the Chat

Navigate to `http://localhost:8000/chat`

Try these example queries:
- "Show me my unread emails"
- "When did I get a confirmation mail for my holiday?"
- "Create a calendar event for the cinema on Friday at 7pm"
- "What events do I have next week?"

## API Endpoints

### Send Message
```
POST /api/chat/send

Request:
{
  "message": "Your question here",
  "history": [] // Optional: previous messages
}

Response:
{
  "success": true,
  "message": "AI response",
  "history": [...], // Updated conversation history
  "tools_used": [] // Tools that were called
}
```

### Get Available Tools
```
GET /api/chat/tools

Response:
{
  "tools": [
    {
      "name": "search_emails",
      "description": "...",
      "category": "email"
    },
    ...
  ],
  "plugins": [
    {
      "name": "email",
      "description": "..."
    },
    {
      "name": "calendar",
      "description": "..."
    }
  ]
}
```

## How It Works

### Plugin Flow
```
User Message
  ↓
ChatController receives message
  ↓
AIService calls AI Provider (OpenAI/Anthropic)
  ↓
AI analyzes message + available tools
  ↓
AI decides which tool(s) to call
  ↓
PluginRegistry routes to correct plugin
  ↓
Plugin executes tool with mock data
  ↓
Results returned to AI
  ↓
AI synthesizes final response
  ↓
Response sent to frontend + displayed
```

### Tool Execution
1. **Validation**: Parameters checked against JSON Schema
2. **Routing**: Tool name mapped to correct plugin
3. **Execution**: Plugin executes the tool
4. **Return**: Result wrapped in ToolResult object

## Adding a New Plugin

Adding a new integration is super simple:

### Step 1: Create the Plugin Class

```php
<?php

namespace App\AI\Plugins;

use App\AI\Contracts\PluginInterface;
use App\AI\Contracts\ToolDefinition;
use App\AI\Contracts\ToolResult;

class NotesPlugin implements PluginInterface
{
    public function getName(): string
    {
        return 'notes';
    }

    public function getDescription(): string
    {
        return 'Access and manage your notes';
    }

    public function getTools(): array
    {
        return [
            new ToolDefinition(
                name: 'search_notes',
                description: 'Search notes by keyword',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => [
                            'type' => 'string',
                            'description' => 'Search term',
                        ],
                    ],
                    'required' => ['keyword'],
                ],
            ),
            // More tools...
        ];
    }

    public function executeTool(string $toolName, array $parameters): ToolResult
    {
        return match ($toolName) {
            'search_notes' => $this->searchNotes($parameters),
            // More tools...
            default => ToolResult::failure("Tool not found"),
        };
    }

    private function searchNotes(array $params): ToolResult
    {
        // Implementation here
        return ToolResult::success(['notes' => []]);
    }
}
```

### Step 2: Register in Service Provider

Edit `app/Providers/AIServiceProvider.php`:

```php
public function register(): void
{
    $this->app->singleton(PluginRegistry::class, function () {
        $registry = new PluginRegistry();

        $registry->register(new EmailPlugin());
        $registry->register(new CalendarPlugin());
        $registry->register(new NotesPlugin()); // Add this line

        return $registry;
    });
    // ...
}
```

### Step 3: Done!

The plugin is now available. The AI will automatically discover and use your new tools.

## Migrating to Real APIs

### Email Plugin → Gmail API

Replace the mock data loading with real Gmail API calls:

```php
use Google\Service\Gmail;

public function searchEmails(array $params): ToolResult
{
    $gmail = app(Gmail::class);

    $query = "is:unread"; // Build query from params
    $results = $gmail->users_messages->listUsersMessages('me', [
        'q' => $query,
        'maxResults' => 10,
    ]);

    // Process and return results
    return ToolResult::success([...]);
}
```

### Calendar Plugin → Google Calendar API

```php
use Google\Service\Calendar;

public function createEvent(array $params): ToolResult
{
    $event = new \Google\Service\Calendar\Event([
        'summary' => $params['title'],
        'start' => ['dateTime' => $params['start_time']],
        'end' => ['dateTime' => $params['end_time']],
    ]);

    $calendar = app(Calendar::class);
    $created = $calendar->events->insert('primary', $event);

    return ToolResult::success(['event' => $created]);
}
```

**No changes to the core system are needed!** Plugins are self-contained.

## Configuration Options

### AI Provider (`config/ai.php`)

```php
return [
    'provider' => env('AI_PROVIDER', 'openai'), // 'openai' or 'anthropic'

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4-turbo-preview'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-20241022'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
    ],

    'system_prompt' => 'You are a helpful AI assistant...',
    'max_history' => env('AI_MAX_HISTORY', 20),
    'max_tokens' => env('AI_MAX_TOKENS', 2048),
];
```

## Understanding the Mock Data

### Email Plugin
- 7 mock emails with realistic content
- Emails about holidays, projects, cinema, events, etc.
- Mix of read and unread emails
- Date range: January 15 - February 5, 2026

### Calendar Plugin
- 6 mock events
- Events: team meetings, doctor appointment, cinema, lunch, project deadline, holiday trip
- Dates span from Feb 10 to Mar 6, 2026
- Realistic descriptions and locations

This mock data lets you test the full system before connecting real APIs.

## Design Patterns Used

### 1. **Plugin Architecture**
- Contracts define interfaces
- Plugins implement contracts
- Registry manages plugins
- Perfect for adding new integrations

### 2. **Dependency Injection**
- AIService injected into controller
- PluginRegistry injected into AIService
- Service provider handles configuration

### 3. **Provider Pattern**
- OpenAI and Anthropic both supported
- Can switch providers by changing config
- Tool format conversion automatic

### 4. **Parameter Validation**
- JSON Schema-based
- Type checking
- Length/enum constraints
- Validation before execution

### 5. **Error Handling**
- Tool failures return ToolResult with error
- Parameter validation errors caught
- API errors handled gracefully
- Always returns valid JSON response

## Security Considerations

1. **CSRF Protection**: X-CSRF-TOKEN header sent automatically by API wrapper
2. **Parameter Validation**: All inputs validated against JSON Schema before execution
3. **API Keys**: Store in `.env` (never commit)
4. **Error Messages**: Don't expose sensitive system info in errors
5. **Rate Limiting**: Can be added to routes using middleware

## Performance Tips

1. **Conversation History**: Limited to configurable max (default 20 messages)
2. **Mock Data**: Hardcoded for instant responses
3. **Plugin Registry**: Singleton - loaded once at boot
4. **Parameter Validation**: Fast JSON Schema checks
5. **AI Calls**: Only made when needed

## Troubleshooting

### "API error: 401 Unauthorized"
- Check `OPENAI_API_KEY` or `ANTHROPIC_API_KEY` in `.env`
- Verify correct provider set in `AI_PROVIDER`

### "Tool not found"
- Verify plugin is registered in AIServiceProvider
- Check tool name matches exactly

### "Parameter validation failed"
- Check parameter names and types
- Verify required parameters are provided
- Look at tool definition for expected format

### Chat not responding
- Check browser console for errors
- Verify API key is set
- Check `/api/chat/tools` endpoint returns data

## What's Next

### Short Term
1. ✅ Test with mock data
2. Set up real API keys (OpenAI or Anthropic)
3. Implement real Gmail integration
4. Implement real Google Calendar integration

### Medium Term
1. Add more plugins (notes, tasks, weather, etc.)
2. Add conversation export/history
3. Add user preferences for AI behavior
4. Add rate limiting and usage tracking

### Long Term
1. Add voice/speech recognition
2. Add file upload/analysis
3. Add plugin marketplace
4. Add team collaboration features

## File Reference

```
Backend:
- app/AI/Contracts/PluginInterface.php
- app/AI/Contracts/ToolDefinition.php
- app/AI/Contracts/ToolResult.php
- app/AI/Core/PluginRegistry.php
- app/AI/Core/ParameterValidator.php
- app/AI/Services/AIService.php
- app/AI/Plugins/EmailPlugin.php
- app/AI/Plugins/CalendarPlugin.php
- app/Http/Controllers/ChatController.php
- app/Providers/AIServiceProvider.php
- config/ai.php
- routes/web.php
- bootstrap/providers.php

Frontend:
- resources/js/components/ChatBot.svelte
- resources/js/pages/Chat.svelte
- resources/js/stores/routes.svelte.js
- resources/js/pages/Home.svelte (updated with link)
- resources/js/lib/api.js (used for API calls)

Configuration:
- .env (add AI_PROVIDER, API_KEY, etc.)
- .env.example (documented)
```

## Questions & Support

Refer to the code comments and MEMORY.md for detailed implementation notes. Each file is well-documented with inline comments.
