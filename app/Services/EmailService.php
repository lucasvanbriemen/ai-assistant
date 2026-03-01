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

        [
            'name' => 'search_emails',
            'description' => 'Search for emails based on filters and provide a summary.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'keyword' => [
                        'type' => 'string',
                        'description' => 'Search keyword to find in subject or body, (e.g. "project update")',
                    ],
                    'sender' => [
                        'type' => 'string',
                        'description' => 'Filter by sender email address (e.g. alice@example.com)',
                    ],
                    'from_date' => [
                        'type' => 'string',
                        'description' => 'Filter emails from this date (YYYY-MM-DD), any emails before this date will not be included',
                    ],
                    'to_date' => [
                        'type' => 'string',
                        'description' => 'Filter emails until this date (YYYY-MM-DD), any emails after this date will not be included',
                    ],
                    'unread_only' => [
                        'type' => 'boolean',
                        'description' => 'Only return emails that have not been opened yet and are unread',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of emails to return (default: 50, max: 100)',
                    ],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'read_email',
            'description' => 'Read the content of a specific email.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'email_id' => [
                        'type' => 'string',
                        'description' => 'The ID of the email to read.',
                    ],
                ],
                'required' => ['email_id'],
            ],
        ]
    ];

    public const TOOL_FUNCTION_MAP = [
        'search_emails' => 'searchEmails',
        'read_email' => 'readEmail',
    ];
}
