<?php

namespace App\AI\Contracts;

/**
 * Configuration for API-based plugins
 */
class ApiConfig
{
    public function __construct(
        public string $baseUrl,
        public array $endpoints = [],
        public array $headers = []
    ) {}

    /**
     * Get a complete endpoint URL
     */
    public function getEndpointUrl(string $endpoint, array $params = []): string
    {
        $path = $this->endpoints[$endpoint];

        // Replace URL parameters like {id} with actual values
        foreach ($params as $key => $value) {
            $path = str_replace("{{$key}}", urlencode((string)$value), $path);
        }

        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    public function getHeaders(): array
    {
        $headers = array_merge(
            ['Content-Type' => 'application/json'],
            $this->headers
        );

        if ($this->authToken) {
            $headers['Authorization'] = "Bearer ". env('AGENT_TOKEN');
        }

        return $headers;
    }
}
