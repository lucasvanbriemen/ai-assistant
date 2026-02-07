<?php

namespace App\AI\Core;

use App\AI\Contracts\PluginInterface;
use App\AI\Contracts\ToolResult;
use App\AI\Plugins\EmailPlugin;

class PluginList
{
    public static array $plugins = [];
    private static array $pluginToolMap = [];
    private static bool $initialized = false;

    private static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$plugins = [
            new EmailPlugin(),
        ];

        foreach (self::$plugins as $plugin) {
            foreach ($plugin->getTools() as $tool) {
                self::$pluginToolMap[$tool['name']] = $plugin;
            }
        }

        self::$initialized = true;
    }

    /**
     * Get all available tools in OpenAI format
     */
    public static function getToolsInOpenAIFormat(): array
    {
        self::initialize();
        $tools = [];
        foreach (self::$plugins as $plugin) {
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
        self::initialize();
        $plugin = self::$pluginToolMap[$toolName] ?? null;

        if (!$plugin) {
            return ToolResult::failure("Tool '{$toolName}' not found");
        }

        // Validate parameters
        $tool = null;
        foreach ($plugin->getTools() as $t) {
            if ($t['name'] === $toolName) {
                $tool = $t;
                break;
            }
        }

        if (!$tool) {
            return ToolResult::failure("Tool '{$toolName}' not found");
        }

        // Validate parameters against schema
        $errors = ParameterValidator::validate($parameters, $tool['parameters']);
        if (!empty($errors)) {
            return ToolResult::failure('Parameter validation failed: ' . json_encode($errors));
        }

        try {
            return $plugin->executeTool($toolName, $parameters);
        } catch (\Exception $e) {
            return ToolResult::failure('Tool execution error: ' . $e->getMessage());
        }
    }
}
