<?php

namespace App\Services\CardIntel;

use App\Models\CardIntelScan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CardIntelStorageService
{
    protected string $disk = 'public';
    protected string $directory = 'cardintel';

    /**
     * Allowed MIME types for business card images.
     */
    protected const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
        'image/heic',
        'image/heif',
        'application/pdf',
    ];

    /**
     * Maximum file size in bytes (10MB).
     */
    protected const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /**
     * Store an uploaded file and return storage info.
     */
    public function store(UploadedFile $file, int $userId): array
    {
        // Validate file
        $this->validateFile($file);

        // Generate unique filename
        // Use original extension, or derive from MIME type for mobile camera photos
        $extension = $file->getClientOriginalExtension();
        if (empty($extension)) {
            $extension = $this->getExtensionFromMimeType($file->getMimeType());
        }
        $filename = Str::uuid() . '.' . $extension;

        // Create user directory path
        $userDir = "{$this->directory}/{$userId}";
        $path = "{$userDir}/{$filename}";

        // Store the file
        Storage::disk($this->disk)->putFileAs($userDir, $file, $filename);

        // Get public URL
        $url = Storage::disk($this->disk)->url($path);

        return [
            'file_path' => $path,
            'file_url' => $url,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ];
    }

    /**
     * Store a file from base64 data.
     */
    public function storeFromBase64(string $base64Data, int $userId, string $extension = 'jpg'): array
    {
        // Decode base64
        $data = base64_decode($base64Data);

        if ($data === false) {
            throw new \InvalidArgumentException('Invalid base64 data');
        }

        // Generate unique filename
        $filename = Str::uuid() . '.' . $extension;
        $userDir = "{$this->directory}/{$userId}";
        $path = "{$userDir}/{$filename}";

        // Store the file
        Storage::disk($this->disk)->put($path, $data);

        // Get public URL
        $url = Storage::disk($this->disk)->url($path);

        return [
            'file_path' => $path,
            'file_url' => $url,
            'size' => strlen($data),
        ];
    }

    /**
     * Validate uploaded file.
     */
    protected function validateFile(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException(
                'Plik jest za duży. Maksymalny rozmiar: ' . $this->formatBytes(self::MAX_FILE_SIZE)
            );
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new \InvalidArgumentException(
                'Nieobsługiwany format pliku. Dozwolone: JPG, PNG, WebP, HEIC, PDF'
            );
        }

        // Check if file is readable
        if (!$file->isReadable()) {
            throw new \InvalidArgumentException('Nie można odczytać pliku');
        }
    }

    /**
     * Get absolute path to a stored file.
     */
    public function getAbsolutePath(string $storagePath): string
    {
        return Storage::disk($this->disk)->path($storagePath);
    }

    /**
     * Get public URL for a stored file.
     */
    public function getPublicUrl(string $storagePath): string
    {
        return Storage::disk($this->disk)->url($storagePath);
    }

    /**
     * Check if a file exists.
     */
    public function exists(string $storagePath): bool
    {
        return Storage::disk($this->disk)->exists($storagePath);
    }

    /**
     * Delete a stored file.
     */
    public function delete(string $storagePath): bool
    {
        if ($this->exists($storagePath)) {
            return Storage::disk($this->disk)->delete($storagePath);
        }

        return false;
    }

    /**
     * Delete all files for a scan.
     */
    public function deleteForScan(CardIntelScan $scan): bool
    {
        return $this->delete($scan->file_path);
    }

    /**
     * Clean up old files (older than specified days).
     */
    public function cleanupOldFiles(int $days = 30): int
    {
        $deleted = 0;
        $cutoff = now()->subDays($days);

        // Get all files in the directory
        $files = Storage::disk($this->disk)->allFiles($this->directory);

        foreach ($files as $file) {
            $lastModified = Storage::disk($this->disk)->lastModified($file);

            if ($lastModified < $cutoff->timestamp) {
                Storage::disk($this->disk)->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Get storage statistics.
     */
    public function getStorageStats(int $userId = null): array
    {
        $directory = $userId
            ? "{$this->directory}/{$userId}"
            : $this->directory;

        $files = Storage::disk($this->disk)->allFiles($directory);
        $totalSize = 0;

        foreach ($files as $file) {
            $totalSize += Storage::disk($this->disk)->size($file);
        }

        return [
            'file_count' => count($files),
            'total_size' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
        ];
    }

    /**
     * Format bytes to human readable string.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 2) . ' ' . $units[$index];
    }

    /**
     * Get file extension from MIME type.
     * Used when mobile camera photos don't have an extension.
     */
    protected function getExtensionFromMimeType(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            'application/pdf' => 'pdf',
            default => 'jpg', // Fallback to jpg
        };
    }
}
