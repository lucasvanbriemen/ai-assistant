<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Services\MemoryService;
use App\Services\ReminderService;
use App\Services\CalendarService;
use App\Services\EmailService;
use App\Services\SlackService;
use App\Services\GithubService;
use App\Services\WebService;

class AIService
{
    private const PLUGINS = [
        MemoryService::class,
        ReminderService::class,
        CalendarService::class,
        EmailService::class,
        SlackService::class,
        GithubService::class,
        WebService::class,
    ];

    public const BASE_URL = 'https://api.anthropic.com/v1/messages';
    public const MODEL = 'claude-opus-4-6';
    private const SYSTEM_PROMPT = <<<SYSTEM
        Your name is Prime, that stands for "Personal Responsive Intelligent Manager for Everything". Your purpose is to assist the user in any way possible.

        Always be honest and transparent about your capabilities and limitations. If you don't know something, say so. If you can't do something, explain why.
        Be concise and to the point. Avoid unnecessary words and filler. Focus on providing clear and actionable information.
        Dont make information up. If you don't know the answer, say so. Don't try to guess or fabricate information.
        The same goes for any idea's that i might give. be honest about the feasibility of the idea's and don't try to make them work if they are not feasible. If an idea is not feasible, explain why and suggest alternatives if possible.
        Keep your awnser as short as possible while still providing all necessary information. Avoid long explanations and tangents. Focus on the core of the question and provide a clear and concise answer.

        The current date and time is: {{current_datetime}}
    SYSTEM;

    private const VOICE_PROMPT = <<<VOICE
        You are speaking to the user through voice in a Discord call. Your response will be read aloud by a text-to-speech engine, so write exactly how you would naturally say it out loud.

        Rules for voice responses:
        - Talk like a real person in a casual conversation. Be natural, not robotic or formal.
        - Never use markdown, bullet points, numbered lists, headings, bold, italic, code blocks, or any formatting.
        - Never spell out URLs, file paths, or technical syntax — paraphrase them instead.
        - Keep responses short. A few sentences is usually enough. If the answer is complex, give the key takeaway first and offer to go deeper.
        - Use contractions and casual phrasing. Say "don't" not "do not", "it's" not "it is".
        - Don't start with filler like "Sure!" or "Great question!". Just answer directly.
    VOICE;

    public static function call($messages, $mode = 'text')
    {
        $usedTools = [];

        return response()->stream(function () use ($messages, $usedTools, $mode) {
            self::callClaude($messages, $usedTools, $mode);
        }, 200, [
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache',
        ]);
    }

    private static function callClaude($messages, &$usedTools = [], $mode = 'text')
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . env('TOKEN'),
            'anthropic-version' => '2023-06-01',
            'anthropic-beta' => 'oauth-2025-04-20',
        ])->withOptions([
            'stream' => true,
        ])->post(self::BASE_URL, [
            'model' => self::MODEL,
            'max_tokens' => 1024,
            'stream' => true,
            'system' => self::buildSystemPrompt($mode),
            'messages' => $messages,
            'temperature' => 0.7,
            'tools' => self::tools(),
        ]);

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $blocks = [];
        $stopReason = null;

        while (! $body->eof()) {
            $buffer .= $body->read(1024);

            // Process all complete events in the buffer (separated by \n\n)
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $chunk = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                // Parse the data: line from the SSE event
                $dataLine = null;
                foreach (explode("\n", $chunk) as $line) {
                    if (str_starts_with($line, 'data: ')) {
                        $dataLine = $line;
                    }
                }

                $data = json_decode(substr($dataLine, 6), true);

                switch ($data['type']) {
                    case 'content_block_start':
                        $index = $data['index'];
                        $block = $data['content_block'];
                        $blocks[$index] = $block;
                        if ($block['type'] === 'tool_use') {
                            $blocks[$index]['input_json'] = '';
                        }
                        break;

                    case 'content_block_delta':
                        $index = $data['index'];
                        $delta = $data['delta'];
                        if ($delta['type'] === 'text_delta') {
                            $blocks[$index]['text'] = ($blocks[$index]['text'] ?? '') . $delta['text'];

                            echo self::formatOutput($delta['text'], $usedTools);

                            ob_flush();
                            flush();
                        } elseif ($delta['type'] === 'input_json_delta') {
                            $blocks[$index]['input_json'] .= $delta['partial_json'];
                        }
                        break;

                    case 'content_block_stop':
                        $index = $data['index'];
                        if (isset($blocks[$index]['input_json'])) {
                            $blocks[$index]['input'] = json_decode($blocks[$index]['input_json'], true) ?? [];
                            unset($blocks[$index]['input_json']);
                        }
                        break;

                    case 'message_delta':
                        if (isset($data['delta']['stop_reason'])) {
                            $stopReason = $data['delta']['stop_reason'];
                        }
                        break;
                }
            }
        }

        // If Claude wants to use tools, execute them and send results back
        if ($stopReason === 'tool_use') {
            $assistantContent = [];
            $toolResults = [];

            foreach ($blocks as $block) {
                if ($block['type'] === 'text') {
                    $assistantContent[] = ['type' => 'text', 'text' => $block['text'] ?? ''];
                } elseif ($block['type'] === 'tool_use') {
                    $assistantContent[] = [
                        'type' => 'tool_use',
                        'id' => $block['id'],
                        'name' => $block['name'],
                        'input' => $block['input'],
                    ];

                    $usedTools[] = $block['name'];

                    $result = self::executeTool($block['name'], $block['input']);

                    $toolResults[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $block['id'],
                        'content' => is_string($result) ? $result : json_encode($result),
                    ];
                }
            }

            // Append the assistant's tool_use response and our tool results, then continue
            $messages[] = ['role' => 'assistant', 'content' => $assistantContent];
            $messages[] = ['role' => 'user', 'content' => $toolResults];

            self::callClaude($messages, $usedTools, $mode);
        }
    }

    private static function formatOutput($textChunk, $usedTools = [])
    {
        return json_encode([
            'data' => [
                'text_chunk' => $textChunk,
                'used_tools' => $usedTools,
            ],
        ]) . "\n";
    }

    private static function tools()
    {
        $tools = [];

        foreach (self::PLUGINS as $plugin) {
            $tools = array_merge($tools, $plugin::TOOLS);
        }

        return $tools;
    }

    private static function executeTool($toolName, $input)
    {
        $plugin = self::findToolClass($toolName);
        $toolFunction = $plugin::TOOL_FUNCTION_MAP[$toolName];
        return $plugin::$toolFunction($input);
    }

    private static function findToolClass($toolName)
    {
        foreach (self::PLUGINS as $plugin) {
            $tools = $plugin::TOOLS;
            foreach ($tools as $tool) {
                if ($tool['name'] === $toolName) {
                    return $plugin;
                }
            }
        }

        return null;
    }

    private static function buildSystemPrompt($mode = 'text')
    {
        $prompt = self::SYSTEM_PROMPT;

        $currentDateTime = now()->toDateTimeString();
        $prompt = str_replace('{{current_datetime}}', $currentDateTime, $prompt);

        if ($mode === 'voice') {
            $prompt .= "\n\n" . self::VOICE_PROMPT;
        }

        return $prompt;
    }
}
