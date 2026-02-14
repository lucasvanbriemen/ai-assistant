<?php

namespace App\AI\Contracts;

/**
 * Configuration for API-based plugins
 */
class ApiConfig
{
    public string $baseUrl;
    public array $endpoints;

    public function __construct($baseUrl = '', $endpoints = [])
    {
        $this->baseUrl = $baseUrl;
        $this->endpoints = $endpoints;
    }

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
        $headers =  ['Content-Type' => 'application/json'];

        if (env('AGENT_TOKEN')) {
            $headers['Authorization'] = "Bearer ". env('AGENT_TOKEN');
        }

        return $headers;
    }
}
