<?php

namespace App\AI\Contracts;

interface PluginInterface
{
    /**
     * Get the plugin name (used for identification)
     */
    public function getName(): string;

    /**
     * Get the plugin description
     */
    public function getDescription(): string;

    /**
     * Get all tools provided by this plugin
     *
     * @return array<ToolDefinition>
     */
    public function getTools(): array;

    /**
     * Execute a tool and return the result
     */
    public function executeTool(string $toolName, array $parameters): ToolResult;
}
