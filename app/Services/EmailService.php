<?php

namespace App\Services;

class EmailService
{
    private const BASE_URL = config('services.email.base_url');
    private const TOKEN = config('services.tool_agent_token');

    public const TOOLS = [
        // Ideas:
        // - list emails based on filters with a preview
        // User: "Show me all emails from Alice"
        // Gets email from Alice (from memory)
        // gets all email contents from api and summarizes them to show in UI
    ];

    public const TOOL_FUNCTION_MAP = [];
}
