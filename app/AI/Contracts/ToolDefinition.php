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

    /**
     * Convert to OpenAI function calling format
     */
    public function toOpenAIFormat(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => $this->parameters['properties'] ?? [],
                    'required' => $this->parameters['required'] ?? [],
                ],
            ],
        ];
    }
}
