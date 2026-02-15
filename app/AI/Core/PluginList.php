<?php

namespace App\AI\Core;

use App\AI\Contracts\PluginInterface;
use App\AI\Contracts\ToolResult;
use App\AI\Plugins\EmailPlugin;
use App\AI\Plugins\MemoryPlugin;
use App\AI\Plugins\CalendarPlugin;

class PluginList
{
    private static function getPlugins(): array
    {
        return [
            new EmailPlugin(),
            new MemoryPlugin(),
            new CalendarPlugin(),
        ];
    }

    /**
     * https://platform.openai.com/docs/guides/function-calling?api-mode=chat
     */
    public static function formatToolsForOpenAI(): array
    {
        $tools = [];
        foreach (self::getPlugins() as $plugin) {
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
        $plugin = self::getPluginByToolName($toolName);

        try {
            return $plugin->executeTool($toolName, $parameters);
        } catch (\Exception $e) {
            return ToolResult::failure('Tool execution error: ' . $e->getMessage());
        }
    }

    private static function getPluginByToolName(string $toolName): ?PluginInterface
    {
        foreach (self::getPlugins() as $plugin) {
            foreach ($plugin->getTools() as $tool) {
                if ($tool['name'] === $toolName) {
                    return $plugin;
                }
            }
        }
        return null;
    }
}
