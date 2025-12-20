<?php

namespace App\Services\AI\Providers;

use App\Services\AI\BaseProvider;

class OllamaProvider extends BaseProvider
{
    protected function getDefaultModel(): string
    {
        return 'llama3.2';
    }

    public function supportsFetchModels(): bool
    {
        return true;
    }

    protected function getDefaultHeaders(): array
    {
        // Ollama doesn't require authentication
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    public function testConnection(): array
    {
        $response = $this->makeRequest('get', 'api/tags');

        if ($response['success']) {
            $models = $response['data']['models'] ?? [];
            $count = count($models);
            return [
                'success' => true,
                'message' => "Połączenie z Ollama działa poprawnie. Znaleziono {$count} modeli.",
            ];
        }

        return [
            'success' => false,
            'message' => 'Błąd połączenia: ' . ($response['error'] ?? 'Sprawdź czy Ollama jest uruchomiona.'),
        ];
    }

    public function fetchAvailableModels(): array
    {
        $response = $this->makeRequest('get', 'api/tags');

        if (!$response['success']) {
            return [];
        }

        $models = [];
        $data = $response['data']['models'] ?? [];

        foreach ($data as $model) {
            $name = $model['name'] ?? '';
            $models[] = [
                'model_id' => $name,
                'display_name' => $this->formatModelName($name),
            ];
        }

        return $models;
    }

    public function generateText(string $prompt, ?string $model = null, array $options = []): string
    {
        $response = $this->makeRequest('post', 'api/generate', [
            'model' => $this->getModel($model),
            'prompt' => $prompt,
            'stream' => false,
            'options' => [
                'num_predict' => $options['max_tokens'] ?? 1024,
                'temperature' => $options['temperature'] ?? 0.7,
            ],
        ]);

        if (!$response['success']) {
            throw new \Exception('Ollama Error: ' . ($response['error'] ?? 'Unknown error'));
        }

        return $response['data']['response'] ?? '';
    }

    private function formatModelName(string $modelId): string
    {
        // Remove version tags like :latest
        $name = explode(':', $modelId)[0];
        
        $names = [
            'llama3.2' => 'Llama 3.2',
            'llama3.1' => 'Llama 3.1',
            'llama3' => 'Llama 3',
            'llama2' => 'Llama 2',
            'mistral' => 'Mistral',
            'mixtral' => 'Mixtral',
            'codellama' => 'Code Llama',
            'gemma2' => 'Gemma 2',
            'gemma' => 'Gemma',
            'phi3' => 'Phi-3',
            'qwen2' => 'Qwen 2',
        ];

        return $names[$name] ?? ucfirst(str_replace(['-', '_'], ' ', $name));
    }
}
