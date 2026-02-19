<?php

namespace App\AI\Plugins;

use App\AI\Contracts\ToolResult;
use App\AI\Contracts\ApiConfig;
use App\AI\Contracts\PluginInterface;
use App\AI\Services\SlackService;

class SlackPlugin extends PluginInterface
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function getApiConfig(): ApiConfig
    {
        return new ApiConfig();
    }

    public function getTools()
    {
        return [
            [
                'name' => 'list_slack_channels',
                'description' => 'List Slack channels, DMs, and group conversations in the workspace',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'types' => [
                            'type' => 'string',
                            'description' => 'Comma-separated channel types to include: public_channel, private_channel, mpim (group DMs), im (DMs). Default: all types.',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of channels to return (default: 50, max: 200)',
                        ],
                        'exclude_archived' => [
                            'type' => 'boolean',
                            'description' => 'Exclude archived channels (default: true)',
                        ],
                    ],
                    'required' => [],
                ],
            ],

            [
                'name' => 'list_slack_users',
                'description' => 'List workspace users with their names, emails, and IDs. Use this to find user IDs for sending DMs.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of users to return (default: 50, max: 200)',
                        ],
                    ],
                    'required' => [],
                ],
            ],

            [
                'name' => 'search_slack_messages',
                'description' => 'Full-text search across all Slack messages. Supports Slack search modifiers like from:user, in:channel, has:link, before:date, after:date.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Search query. Supports Slack modifiers: from:username, in:channel, has:link, has:reaction, before:YYYY-MM-DD, after:YYYY-MM-DD',
                        ],
                        'count' => [
                            'type' => 'integer',
                            'description' => 'Number of results to return (default: 20, max: 100)',
                        ],
                        'sort' => [
                            'type' => 'string',
                            'description' => 'Sort order: "score" (relevance) or "timestamp" (newest first, default)',
                            'enum' => ['score', 'timestamp'],
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],

            [
                'name' => 'get_slack_conversation_history',
                'description' => 'Get recent messages from a Slack channel. Provide channel_name (e.g. "backend") to read a channel directly - no need to list channels first. Bot/app messages (Sentry, GitHub, etc.) are excluded by default.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'channel_name' => [
                            'type' => 'string',
                            'description' => 'Channel name without # (e.g. "backend", "general"). Preferred over channel_id.',
                        ],
                        'channel_id' => [
                            'type' => 'string',
                            'description' => 'The Slack channel ID. Use channel_name instead when possible.',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Number of human messages to retrieve (default: 50, max: 100)',
                        ],
                        'oldest' => [
                            'type' => 'string',
                            'description' => 'Only show messages after this Unix timestamp',
                        ],
                        'latest' => [
                            'type' => 'string',
                            'description' => 'Only show messages before this Unix timestamp',
                        ],
                        'exclude_bots' => [
                            'type' => 'boolean',
                            'description' => 'Exclude bot/app messages like Sentry, GitHub, etc. (default: true)',
                        ],
                    ],
                    'required' => [],
                ],
            ],

            [
                'name' => 'send_slack_message',
                'description' => 'Send a message to a Slack channel or DM. Provide either a channel name/ID or a user_id to send a DM.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'channel' => [
                            'type' => 'string',
                            'description' => 'Channel name (e.g. "general") or channel ID. Use this for posting to channels.',
                        ],
                        'user_id' => [
                            'type' => 'string',
                            'description' => 'User ID for sending a direct message. Opens a DM conversation automatically.',
                        ],
                        'text' => [
                            'type' => 'string',
                            'description' => 'Message text to send. Supports Slack markdown (*bold*, _italic_, `code`, ```code block```, etc.)',
                        ],
                        'thread_ts' => [
                            'type' => 'string',
                            'description' => 'Timestamp of a parent message to reply in a thread',
                        ],
                    ],
                    'required' => ['text'],
                ],
            ],

            [
                'name' => 'search_slack_files',
                'description' => 'Search for files and images shared in Slack. Filter by type, channel, and date range.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'channel' => [
                            'type' => 'string',
                            'description' => 'Channel ID to filter files from a specific channel',
                        ],
                        'types' => [
                            'type' => 'string',
                            'description' => 'Comma-separated file types: images, snippets, pdfs, docs, zips, all (default: all)',
                        ],
                        'query' => [
                            'type' => 'string',
                            'description' => 'Search query to filter files by name or content',
                        ],
                        'ts_from' => [
                            'type' => 'string',
                            'description' => 'Start date filter (YYYY-MM-DD)',
                        ],
                        'ts_to' => [
                            'type' => 'string',
                            'description' => 'End date filter (YYYY-MM-DD)',
                        ],
                        'count' => [
                            'type' => 'integer',
                            'description' => 'Number of results to return (default: 20, max: 100)',
                        ],
                    ],
                    'required' => [],
                ],
            ]
        ];
    }

    public function executeTool(string $toolName, array $parameters)
    {
        return match ($toolName) {
            'list_slack_channels' => SlackService::listChannels($parameters),
            'list_slack_users' => SlackService::listUsers($parameters),
            'search_slack_messages' => SlackService::searchMessages($parameters),
            'get_slack_conversation_history' => SlackService::getConversationHistory($parameters),
            'send_slack_message' => SlackService::sendMessage($parameters),
            'search_slack_files' => SlackService::searchFiles($parameters),
            default => ToolResult::failure("Tool '{$toolName}' not found in SlackPlugin"),
        };
    }
}
