<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AIService
{
    public const BASE_URL = 'https://api.anthropic.com/v1/messages';
    public const MODEL = 'claude-opus-4-6';

    private const SYSTEM_PROMPT = <<<SYSTEM
        Your name is Prime, that stands for "Personal Responsive Intelligent Manager for Everything". Your purpose is to assist the user in any way possible.

        Always be honest and transparent about your capabilities and limitations. If you don't know something, say so. If you can't do something, explain why.
        Be concise and to the point. Avoid unnecessary words and filler. Focus on providing clear and actionable information.
        Dont make information up. If you don't know the answer, say so. Don't try to guess or fabricate information.
        The same goes for any idea's that i might give. be honest about the feasibility of the idea's and don't try to make them work if they are not feasible. If an idea is not feasible, explain why and suggest alternatives if possible.
        Keep your awnser as short as possible while still providing all necessary information. Avoid long explanations and tangents. Focus on the core of the question and provide a clear and concise answer.
    SYSTEM;

    public static function call($messages)
    {
        $usedTools = [];

        return response()->stream(function () use ($messages) {
            self::callClaude($messages, $usedTools);
        }, 200, [
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache',
        ]);
    }

    private static function callClaude($messages, &$usedTools = [])
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
            'system' => self::SYSTEM_PROMPT,
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

                    $toolResults[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $block['id'],
                        'content' => self::executeTool($block['name'], $block['input']),
                    ];
                }
            }

            // Append the assistant's tool_use response and our tool results, then continue
            $messages[] = ['role' => 'assistant', 'content' => $assistantContent];
            $messages[] = ['role' => 'user', 'content' => $toolResults];

            self::callClaude($messages, $usedTools);
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
        return [
            [
                'name' => 'get_weather',
                'description' => 'Retrive the current temperature and condiction for a given location. The location should be a city or country name.',
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
        ];
    }

    private static function executeTool($toolName, $input)
    {
        return self::getWeather($input['location']);
    }

    public static function getWeather($location)
    {
        return json_encode([
            'location' => $location,
            'temperature' => '20°C',
            'condition' => 'Sunny',
        ]);
    }
}
