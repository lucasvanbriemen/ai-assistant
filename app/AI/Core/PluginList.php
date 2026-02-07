<?php

namespace App\AI\Core;

use App\AI\Contracts\PluginInterface;
use App\AI\Contracts\ToolResult;
use App\AI\Plugins\EmailPlugin;

class PluginList
{
    public static array $plugins = [];
    private array $pluginToolMap = [];

    public function __construct()
    {
        self::$plugins = [
            new EmailPlugin(),
        ];

        foreach (self::$plugins as $plugin) {
            foreach ($plugin->getTools() as $tool) {
                $this->pluginToolMap[$tool->name] = $plugin;
            }
        }
    }


    /**
     * Get all available tools in OpenAI format
     */
    public static function getToolsInOpenAIFormat(): array
    {
        $tools = [];
        foreach (self::$plugins as $plugin) {
            foreach ($plugin->getTools() as $tool) {
                $tools[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool->name,
                        'description' => $tool->description,
                        'parameters' => [
                            'type' => 'object',
                            'properties' => $tool->parameters['properties'] ?? [],
                            'required' => $tool->parameters['required'] ?? [],
                        ],
                    ],
                ];
            }
        }
        return $tools;
    }

    /**
     * Get all available tools in a generic format
     */
    public static function getAllTools(): array
    {
        $tools = [];
        foreach (self::$plugins as $plugin) {
            foreach ($plugin->getTools() as $tool) {
                $tools[] = [
                    'name' => $tool->name,
                    'description' => $tool->description,
                    'category' => $tool->category ?? $plugin->getName(),
                ];
            }
        }
        return $tools;
    }

    /**
     * Execute a tool
     */
    public static function executeTool(string $toolName, array $parameters): ToolResult
    {
        $plugin = self::$pluginToolMap[$toolName] ?? null;

        if (!$plugin) {
            return ToolResult::failure("Tool '{$toolName}' not found");
        }

        // Validate parameters
        $tool = null;
        foreach ($plugin->getTools() as $t) {
            if ($t->name === $toolName) {
                $tool = $t;
                break;
            }
        }

        if (!$tool) {
            return ToolResult::failure("Tool '{$toolName}' not found");
        }

        // Validate parameters against schema
        $errors = ParameterValidator::validate($parameters, $tool->parameters);
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
