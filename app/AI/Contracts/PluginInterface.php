<?php

namespace App\AI\Contracts;

interface PluginInterface
{
    public function getName(): string;
    public function getDescription(): string;
    public function getTools(): array;
    public function executeTool(string $toolName, array $parameters): ToolResult;
}
