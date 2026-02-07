<?php

namespace App\AI\Contracts;

class ToolDefinition
{
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters,
        public ?string $category = null
    ) {}
}
