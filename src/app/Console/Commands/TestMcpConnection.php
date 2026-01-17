<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Models\McpStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestMcpConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:test-connection
                            {--silent : Do not output anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test MCP server connection to NetSendo API';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $silent = $this->option('silent');

        // Get API key from database first (key marked as MCP with encrypted key), then fall back to ENV
        $mcpKey = ApiKey::getMcpKey();
        $apiKey = $mcpKey?->hasEncryptedKey() ? $this->getPlainKeyForTesting($mcpKey) : null;

        // Fall back to ENV if no database key
        if (empty($apiKey)) {
            $apiKey = config('services.mcp.api_key') ?: env('MCP_API_KEY');
        }

        $apiUrl = config('app.url');

        if (empty($apiKey)) {
            $message = 'MCP API key not configured';

            if (!$silent) {
                $this->error($message);
            }

            McpStatus::recordFailure($message, $apiUrl);
            Log::channel('single')->warning('[MCP] ' . $message);

            return self::FAILURE;
        }

        if (!$silent) {
            $this->info('Testing MCP connection to NetSendo API...');
        }

        try {
            // Test connection by calling the account endpoint
            $response = Http::timeout(10)
                ->withToken($apiKey)
                ->acceptJson()
                ->get("{$apiUrl}/api/v1/account");

            if ($response->successful()) {
                $data = $response->json();
                $version = $data['version'] ?? config('netsendo.version', 'unknown');
                $message = "Connected to NetSendo {$version}";

                McpStatus::recordSuccess($message, $version, $apiUrl);

                if (!$silent) {
                    $this->info("✓ {$message}");
                }

                Log::channel('single')->info('[MCP] Connection test successful', [
                    'version' => $version,
                    'api_url' => $apiUrl,
                ]);

                // Mark the key as recently used
                if ($mcpKey) {
                    $mcpKey->markAsUsed();
                }

                // Cleanup old records
                McpStatus::cleanup(100);

                return self::SUCCESS;
            }

            $message = "API returned status {$response->status()}";
            McpStatus::recordFailure($message, $apiUrl);

            if (!$silent) {
                $this->error("✗ {$message}");
            }

            Log::channel('single')->error('[MCP] Connection test failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return self::FAILURE;

        } catch (\Exception $e) {
            // HTTP failed - likely Docker environment where localhost is not reachable
            // Fall back to internal API key verification
            return $this->performInternalTest($mcpKey, $apiKey, $apiUrl, $silent, $e->getMessage());
        }
    }

    /**
     * Get the plain key for testing from encrypted storage.
     * Uses Laravel's Crypt to decrypt the stored key.
     */
    private function getPlainKeyForTesting(ApiKey $mcpKey): ?string
    {
        return $mcpKey->getDecryptedKey();
    }

    /**
     * Perform internal API key verification when HTTP test fails.
     * This is used in Docker environments where localhost is not reachable from container.
     */
    private function performInternalTest(?ApiKey $mcpKey, string $apiKey, string $apiUrl, bool $silent, string $httpError): int
    {
        // Verify the API key is valid by looking it up in the database
        $foundKey = ApiKey::findByKey($apiKey);

        if ($foundKey && !$foundKey->isExpired()) {
            $version = config('netsendo.version', 'unknown');
            $message = "API key verified (internal test) - NetSendo {$version}";

            McpStatus::recordSuccess($message, $version, $apiUrl);

            if (!$silent) {
                $this->info("✓ {$message}");
                $this->warn("  Note: HTTP test failed ({$httpError}), used internal verification.");
            }

            Log::channel('single')->info('[MCP] Internal key verification successful', [
                'version' => $version,
                'api_url' => $apiUrl,
                'http_error' => $httpError,
            ]);

            // Mark the key as recently used
            if ($mcpKey) {
                $mcpKey->markAsUsed();
            }

            McpStatus::cleanup(100);

            return self::SUCCESS;
        }

        // Key verification also failed
        $message = "Connection error: {$httpError}";
        McpStatus::recordFailure($message, $apiUrl);

        if (!$silent) {
            $this->error("✗ {$message}");
        }

        Log::channel('single')->error('[MCP] Connection test exception', [
            'error' => $httpError,
            'api_url' => $apiUrl,
        ]);

        return self::FAILURE;
    }
}

