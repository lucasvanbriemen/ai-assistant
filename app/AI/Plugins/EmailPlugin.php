<?php

namespace App\AI\Plugins;

use App\AI\Contracts\ToolDefinition;
use App\AI\Contracts\ToolResult;
use App\AI\Contracts\ApiConfig;

class EmailPlugin extends ApiBasedPlugin
{
    private array $emails = [];
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Define the API configuration for the email plugin
     */
    protected function getApiConfig(): ApiConfig
    {
        return new ApiConfig(
            baseUrl: env('EMAIL_API_BASE_URL', 'http://localhost:3000'),
            endpoints: [
                'search' => '/api/emails/search',
                'read' => '/api/emails/{id}',
                'unread_count' => '/api/emails/unread/count',
                'send' => '/api/emails/send',
            ],
            headers: [],
            authToken: env('EMAIL_API_AUTH_TOKEN', null),
        );
    }

    public function getName(): string
    {
        return 'email';
    }

    public function getDescription(): string
    {
        return 'Access and manage emails in your inbox';
    }

    public function getTools(): array
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

            new ToolDefinition(
                name: 'get_unread_count',
                description: 'Get the number of unread emails',
                parameters: [
                    'type' => 'object',
                    'properties' => (object)[],
                    'required' => [],
                ],
                category: 'info'
            ),

            new ToolDefinition(
                name: 'send_email',
                description: 'Send an email to a recipient',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'to' => [
                            'type' => 'string',
                            'description' => 'Recipient email address',
                        ],
                        'subject' => [
                            'type' => 'string',
                            'description' => 'Email subject',
                        ],
                        'body' => [
                            'type' => 'string',
                            'description' => 'Email body content',
                        ],
                    ],
                    'required' => ['to', 'subject', 'body'],
                ],
                category: 'send'
            ),

            new ToolDefinition(
                name: 'extract_email_info',
                description: 'Extract specific information from an email body (e.g., movie title, booking details, dates). Use this when you need detailed information from an email.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'email_id' => [
                            'type' => 'string',
                            'description' => 'The email ID to extract information from',
                        ],
                        'fields' => [
                            'type' => 'array',
                            'description' => 'List of fields to extract (e.g., ["movie_title", "date", "time", "location"])',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                    'required' => ['email_id', 'fields'],
                ],
                category: 'extract'
            ),
        ];
    }

    public function executeTool(string $toolName, array $parameters): ToolResult
    {
        return match ($toolName) {
            'search_emails' => $this->searchEmails($parameters),
            'read_email' => $this->readEmail($parameters),
            'get_unread_count' => $this->getUnreadCount($parameters),
            'send_email' => $this->sendEmail($parameters),
            'extract_email_info' => $this->extractEmailInfo($parameters),
            default => ToolResult::failure("Tool '{$toolName}' not found in EmailPlugin"),
        };
    }

    private function searchEmails(array $params): ToolResult
    {
        return $this->searchEmailsViaApi($params);
    }

    private function searchEmailsViaApi(array $params): ToolResult
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
        return $this->readEmailViaApi($params);
    }

    private function readEmailViaApi(array $params): ToolResult
    {
        $response = $this->apiRequest('read', 'GET', ['id' => $params['email_id']]);

        if (!$response['success']) {
            return ToolResult::failure($response['error']);
        }

        return ToolResult::success($response['data']);
    }

    private function getUnreadCount(array $params): ToolResult
    {
        return $this->getUnreadCountViaApi($params);
    }

    private function getUnreadCountViaApi(array $params): ToolResult
    {
        $response = $this->apiRequest('unread_count', 'GET');

        if (!$response['success']) {
            return ToolResult::failure($response['error']);
        }

        return ToolResult::success($response['data']);
    }

    private function sendEmail(array $params): ToolResult
    {
        return $this->sendEmailViaApi($params);
    }

    private function sendEmailViaApi(array $params): ToolResult
    {
        $response = $this->apiRequest('send', 'POST', [], [], $params);

        if (!$response['success']) {
            return ToolResult::failure($response['error']);
        }

        return ToolResult::success($response['data']);
    }

    private function extractEmailInfo(array $params): ToolResult
    {
        $emailId = $params['email_id'] ?? null;
        $fields = $params['fields'] ?? [];

        if (!$emailId || empty($fields)) {
            return ToolResult::failure("email_id and fields are required");
        }

        // First, read the full email
        $emailResult = $this->readEmail(['email_id' => $emailId]);
        if (!$emailResult->isSuccess()) {
            return $emailResult;
        }

        $emailData = $emailResult->toArray();
        $emailContent = $emailData['subject'] . "\n" . $emailData['body'];

        // Extract information from the email
        $extractedInfo = [];

        foreach ($fields as $field) {
            $extractedInfo[$field] = $this->extractField($field, $emailContent);
        }

        return ToolResult::success([
            'email_id' => $emailId,
            'extracted_fields' => $extractedInfo,
        ]);
    }

    private function extractField(string $fieldName, string $emailContent): mixed
    {
        // Common field extractors using patterns
        switch (strtolower($fieldName)) {
            case 'movie_title':
            case 'film_title':
                // Look for movie/film titles
                if (preg_match('/(?:movie|film|watching|showing|title)[\s:]*([^\n\r]+)/i', $emailContent, $matches)) {
                    return trim($matches[1]);
                }
                // Look for "Original Version" or similar
                if (preg_match('/Original Version\s+([^\n\r]*)/i', $emailContent, $matches)) {
                    return trim($matches[1]) ?: 'Original Version';
                }
                return null;

            case 'date':
            case 'event_date':
                // Look for dates in various formats
                if (preg_match('/(?:date|scheduled|on|at)[\s:]*(\d{1,2}\s+\w+\s+\d{4}|\d{4}-\d{2}-\d{2})/i', $emailContent, $matches)) {
                    return trim($matches[1]);
                }
                return null;

            case 'time':
            case 'event_time':
                // Look for times
                if (preg_match('/(?:time|at|@)[\s:]*(\d{1,2}:\d{2}(?:\s*(?:AM|PM|am|pm))?)/i', $emailContent, $matches)) {
                    return trim($matches[1]);
                }
                return null;

            case 'location':
            case 'venue':
                // Look for locations
                if (preg_match('/(?:location|venue|cinema|theater|theatre|address)[\s:]*([^\n\r]+)/i', $emailContent, $matches)) {
                    return trim($matches[1]);
                }
                return null;

            case 'seat':
            case 'seats':
                // Look for seat information
                if (preg_match('/(?:seat|row)[\s:]*([A-Z]?\d+[A-Z]?[\s,]*(?:and\s+)?[A-Z]?\d+[A-Z]?)?/i', $emailContent, $matches)) {
                    return trim($matches[1]);
                }
                return null;

            case 'confirmation_number':
            case 'booking_number':
            case 'reservation_number':
                // Look for confirmation/booking/reservation numbers
                if (preg_match('/(?:confirmation|booking|reservation|order|ticket)\s+(?:number|no\.?|#)[\s:]*([A-Z0-9]+)/i', $emailContent, $matches)) {
                    return trim($matches[1]);
                }
                return null;

            default:
                // For unknown fields, return a summary of content that might contain the field name
                if (preg_match('/' . preg_quote($fieldName) . '[^:\n]*:?\s*([^\n\r]+)/i', $emailContent, $matches)) {
                    return trim($matches[1]);
                }
                return null;
        }
    }
}
