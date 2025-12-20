<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;

class SubscriptionForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'contact_list_id',
        'name',
        'slug',
        'status',
        'type',
        'fields',
        'custom_field_ids',
        'styles',
        'layout',
        'label_position',
        'show_placeholders',
        'double_optin',
        'require_policy',
        'policy_url',
        'redirect_url',
        'success_message',
        'error_message',
        'coregister_lists',
        'coregister_optional',
        'captcha_enabled',
        'captcha_provider',
        'captcha_site_key',
        'captcha_secret_key',
        'honeypot_enabled',
        'submissions_count',
        'last_submission_at',
    ];

    protected $casts = [
        'fields' => 'array',
        'custom_field_ids' => 'array',
        'styles' => 'array',
        'coregister_lists' => 'array',
        'show_placeholders' => 'boolean',
        'double_optin' => 'boolean',
        'require_policy' => 'boolean',
        'coregister_optional' => 'boolean',
        'captcha_enabled' => 'boolean',
        'honeypot_enabled' => 'boolean',
        'submissions_count' => 'integer',
        'last_submission_at' => 'datetime',
    ];

    /**
     * Default styles for new forms
     */
    public static array $defaultStyles = [
        // Form container
        'bgcolor' => '#F2F2F2',
        'border_color' => '#E5E7EB',
        'text_color' => '#1F2937',
        'border_radius' => 8,
        'border_width' => 1,
        'padding' => 24,
        
        // Fields
        'field_bgcolor' => '#FFFFFF',
        'field_text' => '#374151',
        'field_bgcolor_active' => '#F9FAFB',
        'field_text_active' => '#111827',
        'field_border_color' => '#D1D5DB',
        'field_border_color_active' => '#6366F1',
        'field_border_radius' => 6,
        'field_height' => 42,
        'fields_vertical_margin' => 16,
        'fields_horizontal_margin' => 0,
        
        // Submit button
        'submit_color' => '#6366F1',
        'submit_text_color' => '#FFFFFF',
        'submit_border_color' => '#6366F1',
        'submit_hover_color' => '#4F46E5',
        'submit_border_radius' => 6,
        'submit_padding_h' => 24,
        'submit_padding_v' => 12,
        'submit_font_size' => 16,
        'submit_font_weight' => 600,
        'submit_text' => 'Zapisz się',
        'submit_align' => 'center',
        'submit_full_width' => true,
        
        // Typography
        'font_family' => 'Inter, system-ui, sans-serif',
        'label_font_size' => 14,
        'label_font_weight' => 500,
        'placeholder_color' => '#9CA3AF',
    ];

    /**
     * Default fields for new forms
     */
    public static array $defaultFields = [
        [
            'id' => 'email',
            'type' => 'email',
            'label' => 'Adres e-mail',
            'placeholder' => 'Wpisz swój adres e-mail',
            'required' => true,
            'order' => 1,
        ],
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($form) {
            if (empty($form->slug)) {
                $form->slug = self::generateUniqueSlug();
            }
            if (empty($form->fields)) {
                $form->fields = self::$defaultFields;
            }
            if (empty($form->styles)) {
                $form->styles = self::$defaultStyles;
            }
        });
    }

    /**
     * Generate unique slug for form URLs
     */
    public static function generateUniqueSlug(): string
    {
        do {
            $slug = Str::random(12);
        } while (self::where('slug', $slug)->exists());

        return $slug;
    }

    // ========== RELATIONSHIPS ==========

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contactList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(FormIntegration::class);
    }

    // ========== ACCESSORS ==========

    /**
     * Get the public URL for the form
     */
    public function getPublicUrlAttribute(): string
    {
        return url("/subscribe/{$this->slug}");
    }

    /**
     * Get the iFrame URL for the form
     */
    public function getIframeUrlAttribute(): string
    {
        return url("/subscribe/form/{$this->slug}");
    }

    /**
     * Get the JavaScript embed URL
     */
    public function getJsUrlAttribute(): string
    {
        return url("/subscribe/js/{$this->slug}");
    }

    // ========== MUTATORS (for encrypted fields) ==========

    public function setCaptchaSecretKeyAttribute($value)
    {
        $this->attributes['captcha_secret_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getCaptchaSecretKeyAttribute($value)
    {
        if (!$value) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    // ========== SCOPES ==========

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForList($query, $listId)
    {
        return $query->where('contact_list_id', $listId);
    }

    // ========== HELPERS ==========

    /**
     * Check if form is active and accepting submissions
     */
    public function isAcceptingSubmissions(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Increment submissions counter
     */
    public function incrementSubmissions(): void
    {
        $this->increment('submissions_count');
        $this->update(['last_submission_at' => now()]);
    }

    /**
     * Get custom fields used in this form
     */
    public function getCustomFields()
    {
        if (empty($this->custom_field_ids)) {
            return collect();
        }

        return CustomField::whereIn('id', $this->custom_field_ids)->get();
    }

    /**
     * Check if double opt-in should be used (form setting or list default)
     */
    public function shouldUseDoubleOptin(): bool
    {
        if ($this->double_optin !== null) {
            return $this->double_optin;
        }

        // Inherit from contact list settings
        $listSettings = $this->contactList->settings ?? [];
        return $listSettings['double_optin'] ?? true;
    }
}
