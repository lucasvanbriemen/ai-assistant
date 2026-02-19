<?php

namespace App\AI\Services;

use App\AI\Contracts\ToolResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SlackService
{
    private const BASE_URL = 'https://slack.com/api';

    private static function api(string $method, array $params = []): array
    {
        return Http::withToken(config('services.slack.user_oauth_token', ''))
            ->asForm()
            ->post(self::BASE_URL . '/' . $method, $params)
            ->json();
    }

    private static function openDmChannel(string $userId): ?string
    {
        $response = self::api('conversations.open', ['users' => $userId]);

        return $response['channel']['id'] ?? null;
    }

    private static function resolveChannelByName(string $channelName): ?string
    {
        $channelName = ltrim($channelName, '#');

        // Check the cached channel map first
        $channelMap = self::getChannelMap();

        if (isset($channelMap[$channelName])) {
            return $channelMap[$channelName];
        }

        return null;
    }

    private static function getChannelMap(): array
    {
        return Cache::remember('slack_channel_map', 3600, function () {
            $channelMap = [];
            $cursor = null;

            do {
                $params = [
                    'types' => 'public_channel,private_channel',
                    'limit' => 200,
                    'exclude_archived' => 'true',
                ];
                if ($cursor) {
                    $params['cursor'] = $cursor;
                }

                $response = self::api('conversations.list', $params);
                if (!$response['ok']) {
                    break;
                }

                foreach ($response['channels'] ?? [] as $channel) {
                    $channelMap[$channel['name']] = $channel['id'];
                }

                $cursor = $response['response_metadata']['next_cursor'] ?? '';
            } while (!empty($cursor));

            return $channelMap;
        });
    }

    private static function getUserMap(): array
    {
        return Cache::remember('slack_user_map', 86400, function () {
            $userMap = [];
            $response = self::api('users.list', ['limit' => 200]);

            if ($response['ok'] ?? false) {
                foreach ($response['members'] ?? [] as $member) {
                    $displayName = $member['profile']['display_name'] ?? '';
                    $realName = $member['real_name'] ?? '';
                    $username = $member['name'] ?? '';

                    $userMap[$member['id']] = $displayName ?: $realName ?: $username ?: $member['id'];
                }
            }

            return $userMap;
        });
    }

    private static function resolveUserName(string $userId): string
    {
        $userMap = self::getUserMap();
        return $userMap[$userId] ?? $userId;
    }

    private static function formatTimestamp(?string $ts): ?string
    {
        if (!$ts) {
            return null;
        }

        return date('Y-m-d H:i:s', (int) floatval($ts));
    }

    private static function compact(array $data): array
    {
        return array_filter($data, fn($v) => $v !== null && $v !== '' && $v !== []);
    }

    private static function formatMessage(array $message): array
    {
        $userId = $message['user'] ?? $message['bot_id'] ?? null;
        $text = $message['text'] ?? '';

        // Cap message text at 500 chars to prevent context overflow
        if (strlen($text) > 500) {
            $text = substr($text, 0, 500) . '...';
        }

        $formatted = [
            'user' => $userId ? self::resolveUserName($userId) : null,
            'text' => $text,
            'time' => self::formatTimestamp($message['ts'] ?? null),
            'ts' => $message['ts'] ?? null,
        ];

        // Only include optional fields when present
        if (!empty($message['thread_ts'])) {
            $formatted['thread_ts'] = $message['thread_ts'];
        }
        if (!empty($message['reply_count'])) {
            $formatted['reply_count'] = $message['reply_count'];
        }
        if (!empty($message['reactions'])) {
            $formatted['reactions'] = array_map(fn($r) => $r['name'] . ':' . $r['count'], $message['reactions']);
        }
        if (!empty($message['files'])) {
            $formatted['files'] = array_map(fn($f) => self::compact([
                'id' => $f['id'],
                'name' => $f['name'] ?? null,
                'filetype' => $f['filetype'] ?? null,
            ]), $message['files']);
        }

        return $formatted;
    }

    private static function formatChannel(array $channel): array
    {
        // Determine type as a single string instead of multiple booleans
        $type = 'channel';
        if ($channel['is_im'] ?? false) $type = 'dm';
        elseif ($channel['is_mpim'] ?? false) $type = 'group_dm';
        elseif ($channel['is_private'] ?? false) $type = 'private';

        $formatted = [
            'id' => $channel['id'],
            'name' => $channel['name'] ?? null,
            'type' => $type,
            'members' => $channel['num_members'] ?? null,
            'topic' => $channel['topic']['value'] ?? null,
        ];

        if ($type === 'dm') {
            $userId = $channel['user'] ?? null;
            $formatted['user'] = $userId ? self::resolveUserName($userId) : null;
            $formatted['user_id'] = $userId;
        }

        return self::compact($formatted);
    }

    public static function listChannels(array $params): ToolResult
    {
        $types = $params['types'] ?? 'public_channel,private_channel,mpim,im';
        $limit = min($params['limit'] ?? 50, 200);
        $excludeArchived = $params['exclude_archived'] ?? true;

        $response = self::api('conversations.list', [
            'types' => $types,
            'limit' => $limit,
            'exclude_archived' => $excludeArchived ? 'true' : 'false',
        ]);

        $rawChannels = $response['channels'] ?? [];
        $channels = array_map([self::class, 'formatChannel'], $rawChannels);

        // Separate DMs from other channels to prevent context flooding
        $dms = array_filter($channels, fn($c) => ($c['type'] ?? '') === 'dm');
        $nonDms = array_filter($channels, fn($c) => ($c['type'] ?? '') !== 'dm');

        // Limit DMs to 10 most recent to prevent AI from reading all of them
        $dmCount = count($dms);
        if ($dmCount > 10) {
            $dms = array_slice(array_values($dms), 0, 10);
        }

        $result = array_values(array_merge($nonDms, $dms));
        $message = 'Found ' . count($nonDms) . ' channel(s)';
        if ($dmCount > 0) {
            $message .= ' and ' . $dmCount . ' DM(s)';
            if ($dmCount > 10) {
                $message .= ' (showing 10 most recent DMs - use search_slack_messages with "is:dm" to search all DMs)';
            }
        }

        return ToolResult::success([
            'message' => $message,
            'channels' => $result,
        ]);
    }

    public static function listUsers(array $params): ToolResult
    {
        $limit = min($params['limit'] ?? 50, 200);

        $response = self::api('users.list', [
            'limit' => $limit,
        ]);

        $users = [];
        foreach ($response['members'] ?? [] as $member) {
            if ($member['deleted'] ?? false) {
                continue;
            }

            $displayName = $member['profile']['display_name'] ?? '';
            $realName = $member['real_name'] ?? '';
            $username = $member['name'] ?? '';

            $users[] = self::compact([
                'id' => $member['id'],
                'name' => $displayName ?: $realName ?: $username ?: null,
                'real_name' => $realName ?: null,
                'email' => $member['profile']['email'] ?? null,
                'is_bot' => ($member['is_bot'] ?? false) ? true : null,
                'timezone' => $member['tz'] ?? null,
            ]);
        }

        return ToolResult::success([
            'message' => 'Found ' . count($users) . ' user(s)',
            'users' => $users,
        ]);
    }

    public static function searchMessages(array $params): ToolResult
    {
        if (empty($params['query'])) {
            return ToolResult::failure('query is required for searching messages');
        }

        $count = min($params['count'] ?? 20, 100);
        $sort = $params['sort'] ?? 'timestamp';

        $response = self::api('search.messages', [
            'query' => $params['query'],
            'count' => $count,
            'sort' => $sort,
            'sort_dir' => $sort === 'timestamp' ? 'desc' : 'desc',
        ]);

        $matches = $response['messages']['matches'] ?? [];
        $total = $response['messages']['total'] ?? 0;

        $formattedMessages = array_map(function ($match) {
            $userId = $match['user'] ?? null;
            $text = $match['text'] ?? '';
            if (strlen($text) > 500) {
                $text = substr($text, 0, 500) . '...';
            }
            return self::compact([
                'text' => $text,
                'user' => $userId ? self::resolveUserName($userId) : ($match['username'] ?? null),
                'channel' => $match['channel']['name'] ?? null,
                'time' => self::formatTimestamp($match['ts'] ?? null),
                'ts' => $match['ts'] ?? null,
            ]);
        }, $matches);

        return ToolResult::success([
            'message' => "Found {$total} message(s) matching \"{$params['query']}\" (showing " . count($formattedMessages) . ")",
            'total' => $total,
            'messages' => $formattedMessages,
        ]);
    }

    public static function getConversationHistory(array $params): ToolResult
    {
        // Resolve channel: accept channel_name OR channel_id
        $channelId = $params['channel_id'] ?? null;

        if (empty($channelId) && !empty($params['channel_name'])) {
            $channelId = self::resolveChannelByName($params['channel_name']);
        }

        $excludeBots = $params['exclude_bots'] ?? true;

        // When filtering bots, fetch extra messages to compensate for filtered ones
        $requestedLimit = min($params['limit'] ?? 50, 100);
        $fetchLimit = $excludeBots ? min($requestedLimit * 3, 200) : $requestedLimit;

        $apiParams = [
            'channel' => $channelId,
            'limit' => $fetchLimit,
        ];

        if (!empty($params['oldest'])) {
            $apiParams['oldest'] = $params['oldest'];
        }

        if (!empty($params['latest'])) {
            $apiParams['latest'] = $params['latest'];
        }

        $response = self::api('conversations.history', $apiParams);

        $rawMessages = $response['messages'] ?? [];

        // Filter out bot messages if requested
        if ($excludeBots) {
            $rawMessages = array_filter($rawMessages, function ($msg) {
                // Exclude messages from bots and apps
                if (!empty($msg['bot_id']) || !empty($msg['bot_profile'])) {
                    return false;
                }
                if (($msg['subtype'] ?? '') === 'bot_message') {
                    return false;
                }
                return true;
            });
            $rawMessages = array_values($rawMessages);
        }

        // Apply the requested limit after filtering
        $rawMessages = array_slice($rawMessages, 0, $requestedLimit);

        $messages = array_map([self::class, 'formatMessage'], $rawMessages);

        $channelName = $params['channel_name'] ?? null;
        $label = $channelName ? "#{$channelName}" : $channelId;

        return ToolResult::success([
            'message' => "Retrieved " . count($messages) . " message(s) from {$label}" . ($excludeBots ? ' (bot messages excluded)' : ''),
            'channel' => $label,
            'messages' => $messages,
            'has_more' => $response['has_more'] ?? false,
        ]);
    }

    public static function sendMessage(array $params): ToolResult
    {
        if (empty($params['text'])) {
            return ToolResult::failure('text is required');
        }

        $channelId = null;

        // If user_id is provided, open a DM channel first
        if (!empty($params['user_id'])) {
            $channelId = self::openDmChannel($params['user_id']);
        } else (!empty($params['channel'])) {
            $channel = $params['channel'];
            // If it looks like a channel name (not an ID), resolve it
            if (!str_starts_with($channel, 'C') && !str_starts_with($channel, 'D') && !str_starts_with($channel, 'G')) {
                $resolved = self::resolveChannelByName($channel);
                $channelId = $resolved;
            } else {
                $channelId = $channel;
            }
        }

        $apiParams = [
            'channel' => $channelId,
            'text' => $params['text'],
        ];

        if (!empty($params['thread_ts'])) {
            $apiParams['thread_ts'] = $params['thread_ts'];
        }

        $response = self::api('chat.postMessage', $apiParams);

        return ToolResult::success([
            'message' => 'Message sent successfully',
            'channel' => $response['channel'] ?? $channelId,
            'ts' => $response['ts'] ?? null,
        ]);
    }

    public static function searchFiles(array $params): ToolResult
    {
        $count = min($params['count'] ?? 20, 100);

        $apiParams = [
            'count' => $count,
        ];

        if (!empty($params['channel'])) {
            $apiParams['channel'] = $params['channel'];
        }

        if (!empty($params['types'])) {
            $apiParams['types'] = $params['types'];
        }

        if (!empty($params['query'])) {
            // files.list doesn't have a query param, so we use search.files instead
            $apiParams['query'] = $params['query'];

            if (!empty($params['ts_from'])) {
                $apiParams['query'] .= ' after:' . $params['ts_from'];
            }
            if (!empty($params['ts_to'])) {
                $apiParams['query'] .= ' before:' . $params['ts_to'];
            }

            $response = self::api('search.files', $apiParams);

            $files = $response['files']['matches'] ?? [];
            $total = $response['files']['total'] ?? 0;
        } else {
            // Use files.list for browsing without search query
            if (!empty($params['ts_from'])) {
                $apiParams['ts_from'] = strtotime($params['ts_from']);
            }
            if (!empty($params['ts_to'])) {
                $apiParams['ts_to'] = strtotime($params['ts_to']);
            }

            $response = self::api('files.list', $apiParams);

            $files = $response['files'] ?? [];
            $total = count($files);
        }

        $formattedFiles = array_map(function ($file) {
            $userId = $file['user'] ?? null;
            return self::compact([
                'id' => $file['id'],
                'name' => $file['name'] ?? null,
                'filetype' => $file['filetype'] ?? null,
                'size' => $file['size'] ?? null,
                'user' => $userId ? self::resolveUserName($userId) : null,
                'created' => isset($file['created']) ? date('Y-m-d H:i:s', $file['created']) : null,
            ]);
        }, $files);

        return ToolResult::success([
            'message' => "Found {$total} file(s)" . (!empty($params['query']) ? " matching \"{$params['query']}\"" : '') . " (showing " . count($formattedFiles) . ")",
            'total' => $total,
            'files' => $formattedFiles,
        ]);
    }
}
