<?php

namespace App\AI\Plugins;

use App\AI\Contracts\ToolDefinition;
use App\AI\Contracts\ToolResult;
use App\AI\Contracts\ApiConfig;

class EmailPlugin extends ApiBasedPlugin
{
    private array $emails = [];
    private bool $useApi;

    public function __construct()
    {
        $this->useApi = env('EMAIL_PLUGIN_USE_API', false);
        parent::__construct();

        // Load mock emails as fallback if not using API
        if (!$this->useApi) {
            $this->loadMockEmails();
        }
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
        ];
    }

    public function executeTool(string $toolName, array $parameters): ToolResult
    {
        return match ($toolName) {
            'search_emails' => $this->searchEmails($parameters),
            'read_email' => $this->readEmail($parameters),
            'get_unread_count' => $this->getUnreadCount($parameters),
            'send_email' => $this->sendEmail($parameters),
            default => ToolResult::failure("Tool '{$toolName}' not found in EmailPlugin"),
        };
    }

    private function searchEmails(array $params): ToolResult
    {
        if ($this->useApi) {
            return $this->searchEmailsViaApi($params);
        }

        $results = [];

        foreach ($this->emails as $email) {
            $matches = true;

            // Filter by keyword (search in subject and body)
            if (!empty($params['keyword'])) {
                $keyword = strtolower($params['keyword']);
                $subjectMatch = stripos($email['subject'], $params['keyword']) !== false;
                $bodyMatch = stripos($email['body'], $params['keyword']) !== false;
                $matches = $matches && ($subjectMatch || $bodyMatch);
            }

            // Filter by sender
            if (!empty($params['sender'])) {
                $matches = $matches && (stripos($email['sender'], $params['sender']) !== false);
            }

            // Filter by date range
            if (!empty($params['from_date'])) {
                $matches = $matches && ($email['date'] >= $params['from_date']);
            }
            if (!empty($params['to_date'])) {
                $matches = $matches && ($email['date'] <= $params['to_date']);
            }

            // Filter by unread status
            if (!empty($params['unread_only']) && $params['unread_only'] === true) {
                $matches = $matches && ($email['read'] === false);
            }

            if ($matches) {
                $results[] = [
                    'id' => $email['id'],
                    'subject' => $email['subject'],
                    'sender' => $email['sender'],
                    'date' => $email['date'],
                    'preview' => substr($email['body'], 0, 100) . '...',
                    'unread' => !$email['read'],
                ];
            }
        }

        return ToolResult::success([
            'count' => count($results),
            'emails' => array_slice($results, 0, 10), // Return max 10 results
        ]);
    }

    private function searchEmailsViaApi(array $params): ToolResult
    {
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
        if ($this->useApi) {
            return $this->readEmailViaApi($params);
        }

        $emailId = $params['email_id'];

        foreach ($this->emails as $email) {
            if ($email['id'] === $emailId) {
                // Mark as read
                $email['read'] = true;

                return ToolResult::success([
                    'id' => $email['id'],
                    'subject' => $email['subject'],
                    'sender' => $email['sender'],
                    'date' => $email['date'],
                    'body' => $email['body'],
                ]);
            }
        }

        return ToolResult::failure("Email with ID '{$emailId}' not found");
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
        if ($this->useApi) {
            return $this->getUnreadCountViaApi($params);
        }

        $count = count(array_filter($this->emails, fn($e) => !$e['read']));
        return ToolResult::success(['unread_count' => $count]);
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
        if ($this->useApi) {
            return $this->sendEmailViaApi($params);
        }

        // Mock sending email
        $newEmail = [
            'id' => 'email_sent_' . time(),
            'to' => $params['to'],
            'subject' => $params['subject'],
            'body' => $params['body'],
            'sent_date' => date('Y-m-d H:i:s'),
        ];

        return ToolResult::success([
            'message' => 'Email sent successfully',
            'email' => $newEmail,
        ]);
    }

    private function sendEmailViaApi(array $params): ToolResult
    {
        $response = $this->apiRequest('send', 'POST', [], [], $params);

        if (!$response['success']) {
            return ToolResult::failure($response['error']);
        }

        return ToolResult::success($response['data']);
    }

    private function loadMockEmails(): void
    {
        $this->emails = [
            [
                'id' => 'email_001',
                'subject' => 'Holiday Booking Confirmation',
                'sender' => 'bookings@travelco.com',
                'date' => '2026-01-15',
                'body' => "Dear Customer,\n\nYour holiday booking for a 2-week trip to Spain has been confirmed. Your confirmation number is TRAVEL123456.\n\nTrip Details:\n- Departure: February 20, 2026\n- Return: March 6, 2026\n- Hotel: Barcelona Beach Resort\n- Total Cost: $2,500\n\nBest regards,\nTravelCo Bookings",
                'read' => false,
            ],
            [
                'id' => 'email_002',
                'subject' => 'Project Update - Q1 Review',
                'sender' => 'manager@company.com',
                'date' => '2026-01-20',
                'body' => "Hi Team,\n\nPlease find attached the Q1 project review document. The overall progress is on track. A meeting is scheduled for next Tuesday to discuss the findings.\n\nRegards,\nProject Manager",
                'read' => true,
            ],
            [
                'id' => 'email_003',
                'subject' => 'Cinema Tickets - Friday Night Show',
                'sender' => 'tickets@cinema.com',
                'date' => '2026-02-01',
                'body' => "Your cinema tickets are ready!\n\nMovie: The New Adventure (2D)\nDate: Friday, February 14, 2026\nTime: 7:00 PM\nLocation: Downtown Cinema, Theater 5\nSeats: C12, C13\n\nArrival recommended 15 minutes before showtime.",
                'read' => false,
            ],
            [
                'id' => 'email_004',
                'subject' => 'Monthly Newsletter',
                'sender' => 'news@techblog.com',
                'date' => '2026-02-02',
                'body' => "Welcome to February's tech newsletter! This month we cover the latest developments in AI, cloud computing, and web development. Read about the newest frameworks and best practices.",
                'read' => true,
            ],
            [
                'id' => 'email_005',
                'subject' => 'Doctor Appointment Reminder',
                'sender' => 'clinic@healthcenter.com',
                'date' => '2026-02-03',
                'body' => "This is a reminder that you have an appointment scheduled with Dr. Smith on February 10, 2026 at 3:00 PM for your annual checkup.\n\nPlease arrive 10 minutes early.",
                'read' => false,
            ],
            [
                'id' => 'email_006',
                'subject' => 'Your Order #54321 Has Been Shipped',
                'sender' => 'orders@shop.com',
                'date' => '2026-02-04',
                'body' => "Great news! Your order has been shipped.\n\nTracking number: SHIP987654\nExpected delivery: February 8, 2026\n\nClick here to track your package.",
                'read' => true,
            ],
            [
                'id' => 'email_007',
                'subject' => 'Team Lunch This Friday',
                'sender' => 'hr@company.com',
                'date' => '2026-02-05',
                'body' => "Hi Everyone,\n\nWe're organizing a team lunch this Friday at noon at the Italian restaurant downtown. Please RSVP with your dietary preferences.\n\nLooking forward to seeing you there!",
                'read' => false,
            ],
        ];
    }
}
