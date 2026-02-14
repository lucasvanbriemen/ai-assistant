<?php

namespace App\AI\Contracts;

class ServiceResult
{
    public function __construct(
        public readonly bool $success,
        public readonly mixed $data = null,
        public readonly ?string $message = null,
        public readonly ?string $errorCode = null
    ) {}

    public static function success(mixed $data = null, ?string $message = null): self
    {
        return new self(
            success: true,
            data: $data,
            message: $message
        );
    }

    public static function failure(string $message, ?string $errorCode = null, mixed $data = null): self
    {
        return new self(
            success: false,
            data: $data,
            message: $message,
            errorCode: $errorCode
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'message' => $this->message,
            'error_code' => $this->errorCode,
        ];
    }
}
