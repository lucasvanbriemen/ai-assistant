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

        return response()->stream(function () use ($body) {
            while (! $body->eof()) {
                yield $body->read(1024);
            }
        }, 200, [
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache',
        ]);
    }

    private static function tools()
    {
        return [
            [
                'name' => 'search_web',
                'description' => 'Search the web for information.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The search query.',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'get_weather',
                'description' => 'Get the current weather for a location.',
                'input_schema' => [
                    'type' => 'object',
                    'location' => [
                        'type' => 'string',
                        'description' => 'The location to get the weather for.',
                    ],
                    'required' => ['location'],
                ],
            ],
        ];
    }

    private static function excuteTool($toolName, $input)
    {
        switch ($toolName) {
            case 'search_web':
                return self::searchWeb($input['query']);
            case 'get_weather':
                return self::getWeather($input['location']);
            default:
                throw new \Exception("Unknown tool: $toolName");
        }
    }

    public static function searchWeb($query)
    {
        return json_encode([
            'query' => $query,
            'results' => [
                [
                    'title' => 'Example Result 1',
                    'url' => 'https://example.com/result1',
                    'snippet' => 'This is an example search result.',
                ],
                [
                    'title' => 'Example Result 2',
                    'url' => 'https://example.com/result2',
                    'snippet' => 'This is another example search result.',
                ],
            ],
        ]);
    }

    public static function getWeather($location)
    {
        // Implement your weather fetching logic here, e.g., using an external API
        return json_encode([
            'location' => $location,
            'temperature' => '20°C',
            'condition' => 'Sunny',
        ]);
    }
}
