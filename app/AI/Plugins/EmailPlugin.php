<?php

namespace App\AI\Plugins;

use App\AI\Contracts\ToolDefinition;
use App\AI\Contracts\ToolResult;
use App\AI\Contracts\ApiConfig;

class EmailPlugin extends ApiBasedPlugin
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function getApiConfig(): ApiConfig
    {
        return new ApiConfig(
            baseUrl: env('EMAIL_API_BASE_URL'),
            endpoints: [
                'search' => '/api/emails/search',
                'read' => '/api/emails/{id}'
            ],
            headers: []
        );
    }

    public function getName()
    {
        return 'email';
    }

    public function getDescription()
    {
        return 'Get Information from emails, searching emails and reading them';
    }

    public function getTools()
    {
        return [
            new ToolDefinition(
                name: 'search_emails',
                description: 'Search emails by keyword, sender, date range, or read status',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => [
                            'type' => 'string',
                            'description' => 'Search keyword to find in subject or body',
                        ],
                        'sender' => [
                            'type' => 'string',
                            'description' => 'Filter by sender email address',
                        ],
                        'from_date' => [
                            'type' => 'string',
                            'description' => 'Filter emails from this date (YYYY-MM-DD)',
                        ],
                        'to_date' => [
                            'type' => 'string',
                            'description' => 'Filter emails until this date (YYYY-MM-DD)',
                        ],
                        'unread_only' => [
                            'type' => 'boolean',
                            'description' => 'Only return unread emails',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of emails to return (default: 50, max: 100)',
                        ],
                    ],
                    'required' => [],
                ],
                category: 'search'
            ),

            new ToolDefinition(
                name: 'read_email',
                description: 'Get the full content of an email by ID',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'email_id' => [
                            'type' => 'string',
                            'description' => 'The email ID to read',
                        ],
                    ],
                    'required' => ['email_id'],
                ],
                category: 'read'
            ),
        ];
    }

    public function executeTool(string $toolName, array $parameters)
    {
        return match ($toolName) {
            'search_emails' => $this->searchEmails($parameters),
            'read_email' => $this->readEmail($parameters),
            default => ToolResult::failure("Tool '{$toolName}' not found in EmailPlugin"),
        };
    }

    private function searchEmails(array $params): ToolResult
    {
        // Set a default limit if not provided (default 50, max 100)
        if (!isset($params['limit'])) {
            $params['limit'] = 50;
        } else {
            $params['limit'] = min((int)$params['limit'], 100);
        }

        $response = $this->apiRequest('search', 'GET', [], $params);

        if (!$response['success']) {
            return ToolResult::failure($response['error']);
        }

        $data = $response['data'];
        return ToolResult::success([
            'count' => $data['count'] ?? 0,
            'emails' => $data['emails'] ?? [],
        ]);
    }

    private function readEmail(array $params): ToolResult
    {
        $response = $this->apiRequest('read', 'GET', ['id' => $params['email_id']]);

        if (!$response['success']) {
            return ToolResult::failure($response['error']);
        }

        return ToolResult::success($response['data']);
    }
}
