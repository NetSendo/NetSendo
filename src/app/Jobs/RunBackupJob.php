<?php

namespace App\Jobs;

use App\Models\BackupOperation;
use App\Services\Backup\BackupManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Runs `backup:run` on the queue instead of inside the HTTP request (issue #26).
 *
 * A full-application backup can easily exceed PHP-FPM's request timeout, which
 * is why the old synchronous controller left the user staring at a hung page
 * with no idea whether a backup was produced. The queued job updates a
 * BackupOperation row the settings page polls for progress and result.
 */
class RunBackupJob implements ShouldQueue
{
    use Queueable;

    public $tries = 1;

    /** Full-app archives can be slow; stay under the worker's --max-time=3600. */
    public $timeout = 1800;

    public function __construct(
        public int $operationId,
        public bool $onlyDb = false,
    ) {}

    public function handle(BackupManager $manager): void
    {
        $operation = BackupOperation::find($this->operationId);

        if (! $operation) {
            Log::warning('RunBackupJob: operation not found', ['operation' => $this->operationId]);

            return;
        }

        $operation->markRunning();

        $before = $manager->filenames();
        $output = null;

        try {
            // Symfony treats the mere presence of a boolean option as "set",
            // so only pass --only-db when a database-only backup is requested.
            $params = $this->onlyDb ? ['--only-db' => true] : [];

            $exitCode = Artisan::call('backup:run', $params);
            $output = trim(Artisan::output());

            if ($exitCode !== 0) {
                throw new RuntimeException($output !== '' ? $output : "backup:run exited with code {$exitCode}");
            }

            // Identify the archive this run produced (fall back to the newest).
            $new = array_values(array_diff($manager->filenames(), $before));
            $filename = $new[0] ?? $manager->latestFilename();

            $operation->markSuccess($filename, $output);
        } catch (Throwable $e) {
            Log::error('RunBackupJob failed', [
                'operation' => $this->operationId,
                'error' => $e->getMessage(),
            ]);

            $operation->markFailed($e->getMessage(), $output);
        }
    }

    public function failed(Throwable $e): void
    {
        $operation = BackupOperation::find($this->operationId);

        if ($operation && ! $operation->finished_at) {
            $operation->markFailed($e->getMessage());
        }
    }
}
