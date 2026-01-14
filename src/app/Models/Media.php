<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'user_id',
        'brand_id',
        'folder_id',
        'original_name',
        'stored_path',
        'mime_type',
        'size',
        'width',
        'height',
        'type',
        'alt_text',
        'tags',
        'metadata',
    ];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'tags' => 'array',
        'metadata' => 'array',
    ];

    protected $appends = ['url', 'thumbnail_url'];

    /**
     * Get the user that owns the media.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the brand associated with the media.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the folder containing the media.
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    /**
     * Get extracted colors for the media.
     */
    public function colors(): HasMany
    {
        return $this->hasMany(MediaColor::class);
    }

    /**
     * Get the public URL for the media.
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->stored_path);
    }

    /**
     * Get the thumbnail URL for the media.
     * For now, returns the same as URL. Can be extended to generate thumbnails.
     */
    public function getThumbnailUrlAttribute(): string
    {
        // TODO: Implement thumbnail generation
        return $this->url;
    }

    /**
     * Get formatted file size.
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
     * Get the dominant color.
     */
    public function getDominantColorAttribute(): ?string
    {
        return $this->colors()->where('is_dominant', true)->first()?->hex_color;
    }

    /**
     * Check if media is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Scope to filter only images.
     */
    public function scopeImages($query)
    {
        return $query->where('type', 'image');
    }

    /**
     * Scope to filter only logos.
     */
    public function scopeLogos($query)
    {
        return $query->where('type', 'logo');
    }

    /**
     * Scope to filter by brand.
     */
    public function scopeForBrand($query, $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    /**
     * Scope to filter by folder.
     */
    public function scopeInFolder($query, $folderId)
    {
        return $query->where('folder_id', $folderId);
    }

    /**
     * Scope to search by name or tags.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('original_name', 'like', "%{$search}%")
              ->orWhere('alt_text', 'like', "%{$search}%")
              ->orWhereJsonContains('tags', $search);
        });
    }

    /**
     * Delete the file when deleting the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (Media $media) {
            if (Storage::disk('public')->exists($media->stored_path)) {
                Storage::disk('public')->delete($media->stored_path);
            }
        });
    }
}
