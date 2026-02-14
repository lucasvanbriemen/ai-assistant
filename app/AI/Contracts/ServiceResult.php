<?php

namespace App\AI\Contracts;

class ServiceResult
{
    public function __construct(
        public bool $success,
        public mixed $data = null,
        public ?string $message = null,
    ) {}

    public static function success(mixed $data = null, ?string $message = null): self
    {
        return new self(success: true, data: $data, message: $message);
    }

    public static function failure(string $message): self
    {
        return new self(success: false, message: $message);
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'message' => $this->message,
        ];
    }
}
