<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Central helper for the backup archives created by spatie/laravel-backup.
 *
 * Used by the controller (listing/existence) and the queued jobs
 * (RunBackupJob / RestoreBackupJob). Restore is implemented here because
 * spatie/laravel-backup only creates archives — it has no restore concept.
 */
class BackupManager
{
    public function disk(): string
    {
        return config('backup.backup.destination.disks')[0] ?? 'local';
    }

    public function directory(): string
    {
        return config('backup.backup.name', 'NetSendo');
    }

    public function relativePath(string $filename): string
    {
        return $this->directory() . '/' . basename($filename);
    }

    public function exists(string $filename): bool
    {
        return Storage::disk($this->disk())->exists($this->relativePath($filename));
    }

    /**
     * All backup archives, newest first.
     *
     * @return array<int, array{name:string, size:int, size_human:string, date:string, timestamp:int}>
     */
    public function files(): array
    {
        $disk = $this->disk();

        try {
            $files = Storage::disk($disk)->files($this->directory());
        } catch (Throwable $e) {
            return [];
        }

        return collect($files)
            ->filter(fn ($file) => str_ends_with($file, '.zip'))
            ->map(function ($file) use ($disk) {
                $size = Storage::disk($disk)->size($file);
                $ts = Storage::disk($disk)->lastModified($file);

                return [
                    'name' => basename($file),
                    'size' => $size,
                    'size_human' => $this->humanFileSize($size),
                    'date' => date('Y-m-d H:i:s', $ts),
                    'timestamp' => $ts,
                ];
            })
            ->sortByDesc('timestamp')
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    public function filenames(): array
    {
        return array_map(fn ($f) => $f['name'], $this->files());
    }

    public function latestFilename(): ?string
    {
        return $this->files()[0]['name'] ?? null;
    }

    /** Restore is only possible for a local disk (we need a real filesystem path). */
    public function restoreSupported(): bool
    {
        return config("filesystems.disks.{$this->disk()}.driver") === 'local';
    }

    /**
     * Restore the database from a backup archive: extract the SQL dump that
     * spatie stored under `db-dumps/` and import it with the mysql client.
     * Callers are expected to have taken a safety backup first.
     */
    public function restoreDatabase(string $filename): void
    {
        if (! $this->restoreSupported()) {
            throw new RuntimeException('Database restore is only supported on the local disk.');
        }

        if (! $this->exists($filename)) {
            throw new RuntimeException("Backup file not found: {$filename}");
        }

        $zipPath = Storage::disk($this->disk())->path($this->relativePath($filename));
        $sqlPath = $this->extractDatabaseDump($zipPath);

        try {
            $this->importSqlDump($sqlPath);
        } finally {
            @unlink($sqlPath);
        }
    }

    /** Extract the database dump from the archive to a temp .sql file; returns its path. */
    protected function extractDatabaseDump(string $zipPath): string
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Unable to open the backup archive.');
        }

        $password = config('backup.backup.password');
        if (! empty($password)) {
            $zip->setPassword($password);
        }

        $entry = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false
                && str_starts_with($name, 'db-dumps/')
                && (str_ends_with($name, '.sql') || str_ends_with($name, '.sql.gz'))
            ) {
                $entry = $name;
                break;
            }
        }

        if ($entry === null) {
            $zip->close();
            throw new RuntimeException('No database dump was found inside the backup archive.');
        }

        $tmpDir = storage_path('app/backup-temp');
        if (! is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }

        $stream = $zip->getStream($entry);
        if ($stream === false) {
            $zip->close();
            throw new RuntimeException('Unable to read the database dump — the archive may be encrypted or corrupted.');
        }

        $target = $tmpDir . '/restore-' . uniqid('', true) . '.sql' . (str_ends_with($entry, '.gz') ? '.gz' : '');
        $out = fopen($target, 'wb');
        while (! feof($stream)) {
            fwrite($out, fread($stream, 1024 * 1024));
        }
        fclose($out);
        fclose($stream);
        $zip->close();

        if (str_ends_with($target, '.gz')) {
            $target = $this->gunzip($target);
        }

        return $target;
    }

    protected function gunzip(string $path): string
    {
        $out = substr($path, 0, -3); // strip ".gz"
        $in = gzopen($path, 'rb');
        $fp = fopen($out, 'wb');
        while (! gzeof($in)) {
            fwrite($fp, gzread($in, 1024 * 1024));
        }
        gzclose($in);
        fclose($fp);
        @unlink($path);

        return $out;
    }

    /** Import a plain .sql dump into the default (MySQL/MariaDB) connection. */
    protected function importSqlDump(string $sqlPath): void
    {
        $conn = config('database.default');
        $c = config("database.connections.{$conn}");

        if (($c['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('Database restore currently supports MySQL/MariaDB connections only.');
        }

        // Password is passed via MYSQL_PWD (never on the command line); the
        // dump is streamed from disk via a shell redirect to avoid loading a
        // large file into PHP memory.
        $cmd = 'mysql'
            . ' --host=' . escapeshellarg((string) ($c['host'] ?? '127.0.0.1'))
            . ' --port=' . escapeshellarg((string) ($c['port'] ?? '3306'))
            . ' --user=' . escapeshellarg((string) ($c['username'] ?? 'root'));

        if (! empty($c['unix_socket'])) {
            $cmd .= ' --socket=' . escapeshellarg((string) $c['unix_socket']);
        }

        $cmd .= ' ' . escapeshellarg((string) ($c['database'] ?? ''))
            . ' < ' . escapeshellarg($sqlPath);

        $result = Process::timeout(1800)
            ->env(['MYSQL_PWD' => (string) ($c['password'] ?? '')])
            ->run(['bash', '-c', $cmd]);

        if ($result->failed()) {
            $err = trim($result->errorOutput() ?: $result->output());
            throw new RuntimeException('MySQL import failed: ' . ($err !== '' ? $err : 'unknown error'));
        }
    }

    public function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $i = 0;
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
