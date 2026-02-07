<?php

namespace App\AI\Contracts;

use Illuminate\Support\Facades\Http;

/**
 * Base class for API-based plugins
 */
abstract class PluginInterface
{
    protected ApiConfig $apiConfig;

    /**
     * Get the API configuration for this plugin
     */
    abstract protected function getApiConfig(): ApiConfig;

    /**
     * Initialize the plugin with API config
     */
    public function __construct()
    {
        $this->apiConfig = $this->getApiConfig();
    }

    abstract public function getName();
    abstract public function getDescription();
    abstract public function getTools();
    abstract public function executeTool(string $toolName, array $parameters);

    /**
     * Make an API request to a configured endpoint
     */
    protected function apiRequest(
        string $endpoint,
        string $method = 'GET',
        array $pathParams = [],
        array $queryParams = [],
        array $body = []
    ): array {
        $url = $this->apiConfig->getEndpointUrl($endpoint, $pathParams);

        $request = Http::withHeaders($this->apiConfig->getHeaders())
            ->timeout(30); // 30 second timeout per API call

        if (!empty($queryParams)) {
            $request = $request->withQueryParameters($queryParams);
        }

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url),
            'POST' => $request->post($url, $body),
            'PUT' => $request->put($url, $body),
            'PATCH' => $request->patch($url, $body),
            'DELETE' => $request->delete($url),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        if ($response->failed()) {
            return [
                'success' => false,
                'error' => "API request failed: {$response->status()} - {$response->body()}",
            ];
        }

        return [
            'success' => true,
            'data' => $response->json(),
            'status' => $response->status(),
        ];
    }
}
