<?php

namespace App\AI\Contracts;

interface PluginInterface
{
    public function getName();
    public function getDescription();
    public function getTools();
    public function executeTool(string $toolName, array $parameters);
}
