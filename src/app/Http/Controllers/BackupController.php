<?php

namespace App\Http\Controllers;

use App\Jobs\RestoreBackupJob;
use App\Jobs\RunBackupJob;
use App\Models\BackupOperation;
use App\Services\Backup\BackupManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BackupController extends Controller
{
    public function __construct(protected BackupManager $manager)
    {
    }

    /**
     * Display the backup management page.
     */
    public function index()
    {
        return Inertia::render('Settings/Backup/Index', [
            'backups' => $this->manager->files(),
            'disk' => $this->manager->disk(),
            'restoreSupported' => $this->manager->restoreSupported(),
            'currentOperation' => BackupOperation::current(),
            'lastOperation' => BackupOperation::lastFinished(),
        ]);
    }

    /**
     * Start a new backup (runs on the queue; the page polls for progress).
     */
    public function create(Request $request)
    {
        if (BackupOperation::current()) {
            return back()->with('error', __('backup.operation_in_progress'));
        }

        $operation = BackupOperation::create([
            'user_id' => $request->user()?->id,
            'type' => BackupOperation::TYPE_CREATE,
            'status' => BackupOperation::STATUS_RUNNING,
            'only_db' => $request->boolean('only_db'),
            'started_at' => now(),
        ]);

        RunBackupJob::dispatch($operation->id, $operation->only_db);

        return redirect()->route('settings.backup.index')
            ->with('success', __('backup.backup_started'));
    }

    /**
     * Restore the database from a backup (queued; takes a safety backup first).
     */
    public function restore(Request $request, string $filename)
    {
        $filename = basename($filename);

        if (! $this->manager->restoreSupported()) {
            return back()->with('error', __('backup.restore_not_supported'));
        }

        if (! $this->manager->exists($filename)) {
            abort(404, __('backup.file_not_found'));
        }

        if (BackupOperation::current()) {
            return back()->with('error', __('backup.operation_in_progress'));
        }

        $operation = BackupOperation::create([
            'user_id' => $request->user()?->id,
            'type' => BackupOperation::TYPE_RESTORE,
            'status' => BackupOperation::STATUS_RUNNING,
            'filename' => $filename,
            'started_at' => now(),
        ]);

        RestoreBackupJob::dispatch($operation->id, $filename);

        return redirect()->route('settings.backup.index')
            ->with('success', __('backup.restore_started'));
    }

    /**
     * Download a backup file.
     */
    public function download(string $filename)
    {
        $filename = basename($filename);

        if (! $this->manager->exists($filename)) {
            abort(404, __('backup.file_not_found'));
        }

        return Storage::disk($this->manager->disk())->download($this->manager->relativePath($filename));
    }

    /**
     * Delete a backup file.
     */
    public function destroy(string $filename)
    {
        $filename = basename($filename);

        if (! $this->manager->exists($filename)) {
            abort(404, __('backup.file_not_found'));
        }

        Storage::disk($this->manager->disk())->delete($this->manager->relativePath($filename));

        return redirect()->route('settings.backup.index')
            ->with('success', __('backup.deleted'));
    }
}
