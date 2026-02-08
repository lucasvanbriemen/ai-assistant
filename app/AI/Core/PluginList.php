<?php

namespace App\AI\Core;

use App\AI\Contracts\PluginInterface;
use App\AI\Contracts\ToolResult;
use App\AI\Plugins\EmailPlugin;

class PluginList
{
    public const PLUGINS = [
        new EmailPlugin(),
    ];

    /**
     * Get all available tools in OpenAI format
     */
    public static function getToolsInOpenAIFormat(): array
    {
        $tools = [];
        foreach (self::PLUGINS as $plugin) {
            foreach ($plugin->getTools() as $tool) {
                $tools[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool['name'],
                        'description' => $tool['description'],
                        'parameters' => [
                            'type' => 'object',
                            'properties' => $tool['parameters']['properties'] ?? [],
                            'required' => $tool['parameters']['required'] ?? [],
                        ],
                    ],
                ];
            }
        }
        return $tools;
    }

    public static function executeTool(string $toolName, array $parameters): ToolResult
    {
        $plugin = null;
        foreach (self::PLUGINS as $index_plugin) {
            foreach ($index_plugin->getTools() as $tool) {
                if ($tool['name'] === $toolName) {
                    $plugin = $index_plugin;
                    break 2;
                }
            }
        }

        try {
            return $plugin->executeTool($toolName, $parameters);
        } catch (\Exception $e) {
            return ToolResult::failure('Tool execution error: ' . $e->getMessage());
        }
    }
}
