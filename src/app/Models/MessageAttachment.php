<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MessageAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'user_id',
        'original_name',
        'stored_path',
        'mime_type',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    /**
     * Get the message that owns this attachment.
     */
    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get the user that owns this attachment.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the full path to the stored file.
     */
    public function getFullPath(): string
    {
        return Storage::disk('local')->path($this->stored_path);
    }

    /**
     * Check if the file exists in storage.
     */
    public function fileExists(): bool
    {
        return Storage::disk('local')->exists($this->stored_path);
    }

    /**
     * Get formatted file size (e.g., "1.5 MB").
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Delete the file from storage when deleting the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (MessageAttachment $attachment) {
            if ($attachment->fileExists()) {
                Storage::disk('local')->delete($attachment->stored_path);
            }
        });
    }

    /**
     * Scope to filter only PDF attachments.
     */
    public function scopePdf($query)
    {
        return $query->where('mime_type', 'application/pdf');
    }
}
