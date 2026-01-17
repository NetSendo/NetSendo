<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateMcpConfigCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:config
                            {--type=auto : Configuration type: local, remote, or auto (auto-detect)}
                            {--name=netsendo : Server name in MCP configuration}
                            {--json : Output only JSON without explanations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate MCP configuration for Claude Desktop, Cursor, or VS Code';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->option('type');
        $serverName = $this->option('name');
        $jsonOnly = $this->option('json');

        // Auto-detect type
        if ($type === 'auto') {
            $type = $this->detectInstallationType();
        }

        if (!$jsonOnly) {
            $this->info('');
            $this->info('🔌 NetSendo MCP Configuration Generator');
            $this->info('');
        }

        $config = $type === 'local'
            ? $this->generateLocalConfig($serverName)
            : $this->generateRemoteConfig($serverName);

        $jsonConfig = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($jsonOnly) {
            $this->line($jsonConfig);
            return self::SUCCESS;
        }

        $this->info("Configuration type: <comment>{$type}</comment>");
        $this->info('');
        $this->line('Add this to your AI tool\'s MCP configuration:');
        $this->info('');

        // Show file location hints
        $this->showConfigFileLocations();

        $this->info('');
        $this->line('<fg=cyan>Configuration:</fg=cyan>');
        $this->info('');
        $this->line($jsonConfig);
        $this->info('');

        if ($type === 'remote') {
            $this->warn('⚠️  Replace YOUR_API_KEY_HERE with your actual API key.');
            $this->info('   Generate one in NetSendo: Settings → API Keys');
            $this->info('');
        }

        $this->info('After adding the configuration, restart your AI tool.');

        return self::SUCCESS;
    }

    /**
     * Detect if this is a local Docker installation or remote.
     */
    private function detectInstallationType(): string
    {
        // Check if running inside Docker
        if (file_exists('/.dockerenv')) {
            return 'local';
        }

        // Check if docker-compose.yml exists in expected locations
        $possiblePaths = [
            base_path('../docker-compose.yml'),
            base_path('../../docker-compose.yml'),
            '/var/www/docker-compose.yml',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return 'local';
            }
        }

        // Default to remote if we can't detect Docker
        return 'remote';
    }

    /**
     * Generate configuration for local Docker installation.
     */
    private function generateLocalConfig(string $serverName): array
    {
        // Try to find the docker-compose.yml path
        $dockerComposePath = $this->findDockerComposePath();

        return [
            'mcpServers' => [
                $serverName => [
                    'command' => 'docker',
                    'args' => [
                        'compose',
                        '-f',
                        $dockerComposePath,
                        'run',
                        '--rm',
                        '-i',
                        'mcp',
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate configuration for remote/hosted installation.
     */
    private function generateRemoteConfig(string $serverName): array
    {
        $appUrl = config('app.url', 'https://your-netsendo-domain.com');

        return [
            'mcpServers' => [
                $serverName => [
                    'command' => 'npx',
                    'args' => [
                        '-y',
                        '@netsendo/mcp-client',
                        '--url',
                        $appUrl,
                        '--api-key',
                        'YOUR_API_KEY_HERE',
                    ],
                ],
            ],
        ];
    }

    /**
     * Try to find the docker-compose.yml path.
     */
    private function findDockerComposePath(): string
    {
        // Common paths relative to Laravel app
        $possiblePaths = [
            base_path('../docker-compose.yml'),
            base_path('../../docker-compose.yml'),
            dirname(base_path()) . '/docker-compose.yml',
        ];

        foreach ($possiblePaths as $path) {
            $realPath = realpath($path);
            if ($realPath && file_exists($realPath)) {
                return $realPath;
            }
        }

        // If running in Docker, the path is typically mounted
        if (file_exists('/.dockerenv')) {
            // Return a placeholder - user will need to update
            return '/path/to/NetSendo/docker-compose.yml';
        }

        return '/path/to/NetSendo/docker-compose.yml';
    }

    /**
     * Show configuration file locations for different AI tools.
     */
    private function showConfigFileLocations(): void
    {
        $this->info('<fg=yellow>Configuration file locations:</fg=yellow>');
        $this->info('');
        $this->line('  <fg=cyan>Claude Desktop (macOS):</fg=cyan>');
        $this->line('    ~/Library/Application Support/Claude/claude_desktop_config.json');
        $this->info('');
        $this->line('  <fg=cyan>Claude Desktop (Windows):</fg=cyan>');
        $this->line('    %APPDATA%\\Claude\\claude_desktop_config.json');
        $this->info('');
        $this->line('  <fg=cyan>Cursor IDE:</fg=cyan>');
        $this->line('    Settings → MCP → Add Server');
        $this->info('');
        $this->line('  <fg=cyan>VS Code (with MCP extension):</fg=cyan>');
        $this->line('    .vscode/mcp.json in your project');
    }
}
