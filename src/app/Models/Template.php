<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'preheader',
        'content',
        'content_plain',
        'mjml_content',
        'json_structure',
        'thumbnail',
        'category',
        'category_id',
        'type',
        'settings',
        'is_public',
    ];

    protected $casts = [
        'json_structure' => 'array',
        'settings' => 'array',
        'is_public' => 'boolean',
    ];

    /**
     * Default template settings
     */
    public static function defaultSettings(): array
    {
        return [
            'width' => 600,
            'background_color' => '#f4f4f4',
            'content_background' => '#ffffff',
            'font_family' => 'Arial, Helvetica, sans-serif',
            'primary_color' => '#6366f1',
            'secondary_color' => '#4f46e5',
            'text_color' => '#1e293b',
            'link_color' => '#6366f1',
            'dark_mode' => [
                'background_color' => '#1e293b',
                'content_background' => '#0f172a',
                'text_color' => '#f1f5f9',
                'link_color' => '#818cf8',
            ],
        ];
    }

    /**
     * Get settings with defaults
     */
    public function getSettingsWithDefaults(): array
    {
        return array_merge(self::defaultSettings(), $this->settings ?? []);
    }

    /**
     * Relationship to user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship to category
     */
    public function templateCategory()
    {
        return $this->belongsTo(TemplateCategory::class, 'category_id');
    }

    /**
     * Scope for public templates
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope for starter templates (system templates without user_id)
     */
    public function scopeStarter($query)
    {
        return $query->whereNull('user_id')->where('is_public', true);
    }

    /**
     * Check if this is a starter template
     */
    public function isStarterTemplate(): bool
    {
        return $this->user_id === null && $this->is_public;
    }

    /**
     * Scope for templates by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for email templates (default type)
     */
    public function scopeEmails($query)
    {
        return $query->where(function ($q) {
            $q->where('type', 'email')->orWhereNull('type');
        });
    }

    /**
     * Scope for insert snippets
     */
    public function scopeInserts($query)
    {
        return $query->where('type', 'insert');
    }

    /**
     * Scope for email signatures
     */
    public function scopeSignatures($query)
    {
        return $query->where('type', 'signature');
    }

    /**
     * Scope for all inserts and signatures
     */
    public function scopeSnippets($query)
    {
        return $query->whereIn('type', ['insert', 'signature']);
    }

    /**
     * Scope for templates with content
     */
    public function scopeWithContent($query)
    {
        return $query->whereNotNull('json_structure')
            ->orWhereNotNull('content');
    }

    /**
     * Check if template has blocks structure
     */
    public function hasBlocksStructure(): bool
    {
        return !empty($this->json_structure);
    }

    /**
     * Get blocks from JSON structure
     */
    public function getBlocks(): array
    {
        return $this->json_structure['blocks'] ?? [];
    }

    /**
     * Duplicate the template
     */
    public function duplicate(): self
    {
        $clone = $this->replicate();
        $clone->name = $this->name . ' (kopia)';
        $clone->is_public = false;
        $clone->thumbnail = null;
        $clone->save();

        return $clone;
    }

    /**
     * Available category types
     */
    public static function categoryTypes(): array
    {
        return [
            'newsletter' => [
                'label' => 'Newsletter',
                'icon' => 'newspaper',
            ],
            'promotional' => [
                'label' => 'Promocyjny',
                'icon' => 'megaphone',
            ],
            'transactional' => [
                'label' => 'Transakcyjny',
                'icon' => 'receipt',
            ],
            'ecommerce' => [
                'label' => 'E-commerce',
                'icon' => 'shopping-cart',
            ],
            'welcome' => [
                'label' => 'Powitalny',
                'icon' => 'hand-wave',
            ],
            'notification' => [
                'label' => 'Powiadomienie',
                'icon' => 'bell',
            ],
        ];
    }
}
