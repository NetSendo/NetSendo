<?php

namespace App\Console\Commands;

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

        // Get API key from environment
        $apiKey = config('services.mcp.api_key') ?: env('MCP_API_KEY');
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
            $response = Http::timeout(30)
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
            $message = "Connection error: {$e->getMessage()}";
            McpStatus::recordFailure($message, $apiUrl);

            if (!$silent) {
                $this->error("✗ {$message}");
            }

            Log::channel('single')->error('[MCP] Connection test exception', [
                'error' => $e->getMessage(),
                'api_url' => $apiUrl,
            ]);

            return self::FAILURE;
        }
    }
}
