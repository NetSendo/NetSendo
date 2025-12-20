<?php

namespace App\Services\AI;

use App\Models\AiIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseProvider implements AiProviderInterface
{
    protected AiIntegration $integration;

    public function setIntegration(AiIntegration $integration): self
    {
        $this->integration = $integration;
        return $this;
    }

    /**
     * Get the API key from integration.
     */
    protected function getApiKey(): ?string
    {
        return $this->integration->api_key;
    }

    /**
     * Get the base URL for API requests.
     */
    protected function getBaseUrl(): string
    {
        return $this->integration->getEffectiveBaseUrl();
    }

    /**
     * Get the default model or the specified one.
     */
    protected function getModel(?string $model): string
    {
        return $model ?? $this->integration->default_model ?? $this->getDefaultModel();
    }

    /**
     * Get the default model for this provider.
     */
    abstract protected function getDefaultModel(): string;

    /**
     * Make an HTTP request to the provider API.
     */
    protected function makeRequest(string $method, string $endpoint, array $data = [], array $headers = []): array
    {
        $url = rtrim($this->getBaseUrl(), '/') . '/' . ltrim($endpoint, '/');

        $defaultHeaders = $this->getDefaultHeaders();
        $allHeaders = array_merge($defaultHeaders, $headers);

        try {
            $response = Http::withHeaders($allHeaders)
                ->timeout(30)
                ->$method($url, $data);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('error.message') ?? $response->body(),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error("AI Provider Request Error: " . $e->getMessage(), [
                'provider' => $this->integration->provider,
                'endpoint' => $endpoint,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get default headers for API requests.
     */
    protected function getDefaultHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($apiKey = $this->getApiKey()) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        return $headers;
    }
}
