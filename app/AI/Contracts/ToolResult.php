<?php

namespace App\AI\Contracts;

class ToolResult
{
    public function __construct(
        public bool $success,
        public mixed $data = null,
        public ?string $error = null,
    ) {}

    public static function success(mixed $data): self
    {
        return new self(success: true, data: $data);
    }

    public static function failure(string $error): self
    {
        return new self(success: false, error: $error);
    }

    /**
     * Convert to array for API response
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'error' => $this->error,
        ];
    }
}
