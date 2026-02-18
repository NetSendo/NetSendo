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

    /**
     * Make a streaming HTTP request, yielding raw SSE data lines.
     *
     * Uses cURL directly because Laravel's Http facade buffers the entire response.
     *
     * @return \Generator<string> Yields raw data lines from SSE stream
     */
    protected function makeStreamingRequest(string $url, array $data, array $headers = []): \Generator
    {
        $defaultHeaders = $this->getDefaultHeaders();
        $allHeaders = array_merge($defaultHeaders, $headers);

        // Format headers for cURL
        $curlHeaders = [];
        foreach ($allHeaders as $key => $value) {
            $curlHeaders[] = "{$key}: {$value}";
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        // Buffer for partial lines
        $buffer = '';
        $lines = [];

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$buffer, &$lines) {
            $buffer .= $chunk;

            // Split by newlines, keeping incomplete last line in buffer
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line !== '') {
                    $lines[] = $line;
                }
            }

            return strlen($chunk);
        });

        // Execute in a non-blocking way by processing accumulated lines
        // We need to use curl_multi for true streaming with generators
        $mh = curl_multi_init();
        curl_multi_add_handle($mh, $ch);

        $active = null;

        do {
            $status = curl_multi_exec($mh, $active);

            // Yield any accumulated lines
            while (!empty($lines)) {
                yield array_shift($lines);
            }

            if ($active) {
                // Wait briefly for more data
                curl_multi_select($mh, 0.1);
            }
        } while ($active && $status === CURLM_OK);

        // Yield any remaining buffered content
        if (trim($buffer) !== '') {
            yield trim($buffer);
        }
        while (!empty($lines)) {
            yield array_shift($lines);
        }

        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        curl_multi_close($mh);

        if ($error) {
            throw new \Exception("Streaming request failed: {$error}");
        }

        if ($httpCode >= 400) {
            throw new \Exception("Streaming request failed with HTTP {$httpCode}");
        }
    }

    /**
     * Default streaming implementation: falls back to non-streaming generateText().
     * Providers should override this with real streaming support.
     *
     * @return \Generator<string>
     */
    public function generateTextStream(string $prompt, ?string $model = null, array $options = []): \Generator
    {
        $response = $this->generateText($prompt, $model, $options);
        yield $response;
    }
}

