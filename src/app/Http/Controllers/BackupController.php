<?php

namespace App\Http\Controllers;

use App\Models\ContactList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BackupController extends Controller
{
    /**
     * Display the backup management page
     */
    public function index()
    {
        $backups = $this->getBackupFiles();
        
        return Inertia::render('Settings/Backup/Index', [
            'backups' => $backups,
            'disk' => config('backup.backup.destination.disks')[0] ?? 'local',
        ]);
    }

    /**
     * Create a new backup
     */
    public function create(Request $request)
    {
        try {
            // Run backup artisan command
            Artisan::call('backup:run', [
                '--only-db' => $request->input('only_db', false),
            ]);
            
            return redirect()->route('settings.backup.index')
                ->with('success', __('Backup został utworzony pomyślnie.'));
        } catch (\Exception $e) {
            return redirect()->route('settings.backup.index')
                ->with('error', __('Błąd podczas tworzenia backupu: ') . $e->getMessage());
        }
    }

    /**
     * Download a backup file
     */
    public function download(string $filename)
    {
        $disk = config('backup.backup.destination.disks')[0] ?? 'local';
        $path = config('backup.backup.name', 'NetSendo') . '/' . $filename;
        
        if (!Storage::disk($disk)->exists($path)) {
            abort(404, 'Plik backupu nie istnieje.');
        }
        
        return Storage::disk($disk)->download($path);
    }

    /**
     * Delete a backup file
     */
    public function destroy(string $filename)
    {
        $disk = config('backup.backup.destination.disks')[0] ?? 'local';
        $path = config('backup.backup.name', 'NetSendo') . '/' . $filename;
        
        if (!Storage::disk($disk)->exists($path)) {
            abort(404, 'Plik backupu nie istnieje.');
        }
        
        Storage::disk($disk)->delete($path);
        
        return redirect()->route('settings.backup.index')
            ->with('success', __('Backup został usunięty.'));
    }

    /**
     * Get list of backup files
     */
    protected function getBackupFiles(): array
    {
        $disk = config('backup.backup.destination.disks')[0] ?? 'local';
        $backupName = config('backup.backup.name', 'NetSendo');
        
        try {
            $files = Storage::disk($disk)->files($backupName);
        } catch (\Exception $e) {
            return [];
        }
        
        return collect($files)
            ->filter(fn($file) => str_ends_with($file, '.zip'))
            ->map(function ($file) use ($disk, $backupName) {
                $filename = basename($file);
                return [
                    'name' => $filename,
                    'size' => Storage::disk($disk)->size($file),
                    'size_human' => $this->humanFileSize(Storage::disk($disk)->size($file)),
                    'date' => date('Y-m-d H:i:s', Storage::disk($disk)->lastModified($file)),
                ];
            })
            ->sortByDesc('date')
            ->values()
            ->toArray();
    }

    /**
     * Convert bytes to human readable format
     */
    protected function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
