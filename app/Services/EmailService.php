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
                    'location' => [
                        'type' => 'string',
                        'description' => 'The location to get the weather for. This should be a city or country name.',
                    ],
                ],
                'required' => ['location'],
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
