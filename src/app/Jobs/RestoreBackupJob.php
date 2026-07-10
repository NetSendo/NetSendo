<?php

namespace App\Jobs;

use App\Models\BackupOperation;
use App\Services\Backup\BackupManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Restores the database from a backup archive (issue #26).
 *
 * Before overwriting anything it takes a fresh database-only "safety" backup,
 * so a restore is always reversible. Because importing the dump replaces the
 * whole database — potentially including this very `backup_operations` table —
 * the final status is written resiliently via finalize().
 */
class RestoreBackupJob implements ShouldQueue
{
    use Queueable;

    public $tries = 1;

    public $timeout = 1800;

    public function __construct(
        public int $operationId,
        public string $filename,
    ) {}

    public function handle(BackupManager $manager): void
    {
        $operation = BackupOperation::find($this->operationId);

        if (! $operation) {
            Log::warning('RestoreBackupJob: operation not found', ['operation' => $this->operationId]);

            return;
        }

        $operation->markRunning();

        $startedAt = $operation->started_at ?? now();
        $userId = $operation->user_id;
        $safety = null;

        try {
            // 1) Safety snapshot of the current database before it is replaced.
            $before = $manager->filenames();
            Artisan::call('backup:run', ['--only-db' => true]);
            $safety = array_values(array_diff($manager->filenames(), $before))[0] ?? null;

            // 2) Import the selected archive's database dump.
            $manager->restoreDatabase($this->filename);

            // 3) Record success (the row above may have been overwritten).
            $this->finalize(BackupOperation::STATUS_SUCCESS, null, $safety, $startedAt, $userId);
        } catch (Throwable $e) {
            Log::error('RestoreBackupJob failed', [
                'operation' => $this->operationId,
                'error' => $e->getMessage(),
            ]);

            $this->finalize(BackupOperation::STATUS_FAILED, $e->getMessage(), $safety, $startedAt, $userId);
        }
    }

    /**
     * Persist the outcome resiliently: the restore may have replaced the
     * backup_operations table, so upsert by the original id.
     */
    protected function finalize(string $status, ?string $message, ?string $safety, $startedAt, ?int $userId): void
    {
        try {
            BackupOperation::updateOrCreate(
                ['id' => $this->operationId],
                [
                    'user_id' => $userId,
                    'type' => BackupOperation::TYPE_RESTORE,
                    'status' => $status,
                    'filename' => $this->filename,
                    'safety_backup' => $safety,
                    'message' => $message ? mb_substr($message, 0, 2000) : null,
                    'started_at' => $startedAt,
                    'finished_at' => now(),
                ]
            );
        } catch (Throwable $e) {
            Log::error('RestoreBackupJob: could not record outcome', [
                'operation' => $this->operationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(Throwable $e): void
    {
        $this->finalize(BackupOperation::STATUS_FAILED, $e->getMessage(), null, now(), null);
    }
}
