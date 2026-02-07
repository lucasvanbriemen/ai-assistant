<?php

namespace App\AI\Core;

use App\AI\Contracts\PluginInterface;
use App\AI\Contracts\ToolResult;

class PluginList
{
    private array $plugins = [];

    /**
     * @var array<string, PluginInterface> Map of tool names to plugins
     */
    private array $toolPluginMap = [];

    /**
     * Register a plugin
     */
    public function add(PluginInterface $plugin): void
    {
        $this->plugins[$plugin->getName()] = $plugin;

        // Map all tools from this plugin
        foreach ($plugin->getTools() as $tool) {
            $this->toolPluginMap[$tool->name] = $plugin;
        }
    }

    /**
     * Get all registered plugins
     *
     * @return array<PluginInterface>
     */
    public function getPlugins(): array
    {
        return array_values($this->plugins);
    }

    /**
     * Get a plugin by name
     */
    public function getPlugin(string $name): ?PluginInterface
    {
        return $this->plugins[$name] ?? null;
    }

    /**
     * Get all available tools in OpenAI format
     */
    public function getToolsInOpenAIFormat(): array
    {
        $tools = [];
        foreach ($this->plugins as $plugin) {
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
    public function getAllTools(): array
    {
        $tools = [];
        foreach ($this->plugins as $plugin) {
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
    public function executeTool(string $toolName, array $parameters): ToolResult
    {
        $plugin = $this->toolPluginMap[$toolName] ?? null;

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
