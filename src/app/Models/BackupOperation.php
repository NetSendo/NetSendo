<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single backup or restore run (issue #26).
 *
 * The queued jobs create a row in the `running` state and transition it to
 * `success`/`failed`; the Backup settings page polls the latest row to give
 * the user real feedback instead of a silent, blocking request.
 */
class BackupOperation extends Model
{
    // id is set explicitly by RestoreBackupJob::finalize() (the restore may
    // overwrite this very table), so allow mass assignment of all columns.
    protected $guarded = [];

    protected $casts = [
        'only_db' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public const TYPE_CREATE = 'create';
    public const TYPE_RESTORE = 'restore';

    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    /** The operation currently in progress, if any. */
    public static function current(): ?self
    {
        return static::where('status', self::STATUS_RUNNING)
            ->latest('id')
            ->first();
    }

    /** The most recent finished operation (for the result banner). */
    public static function lastFinished(): ?self
    {
        return static::whereNotNull('finished_at')
            ->latest('id')
            ->first();
    }

    public function markRunning(): void
    {
        $this->forceFill([
            'status' => self::STATUS_RUNNING,
            'started_at' => $this->started_at ?? now(),
        ])->save();
    }

    public function markSuccess(?string $filename = null, ?string $output = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_SUCCESS,
            'filename' => $filename ?? $this->filename,
            'output' => $output ? mb_substr($output, 0, 60000) : $this->output,
            'finished_at' => now(),
        ])->save();
    }

    public function markFailed(string $message, ?string $output = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'message' => mb_substr($message, 0, 2000),
            'output' => $output ? mb_substr($output, 0, 60000) : $this->output,
            'finished_at' => now(),
        ])->save();
    }
}
