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
        'use_list_redirect',
        'success_message',
        'success_title',
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
        'use_list_redirect' => 'boolean',
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

        // Transparency
        'bgcolor_opacity' => 100,

        // Shadow
        'shadow_enabled' => false,
        'shadow_color' => '#000000',
        'shadow_opacity' => 15,
        'shadow_blur' => 20,
        'shadow_x' => 0,
        'shadow_y' => 10,

        // Gradient
        'gradient_enabled' => false,
        'gradient_direction' => 'to bottom right',
        'gradient_from' => '#6366F1',
        'gradient_to' => '#8B5CF6',

        // Animation
        'animation_enabled' => false,
        'animation_type' => 'fadeIn',
    ];

    /**
     * Design presets for quick styling
     */
    public static array $designPresets = [
        'default' => [
            'name' => 'Domyślny',
            'description' => 'Klasyczny, czysty design',
            'styles' => [], // Uses default styles
        ],
        'modern_dark' => [
            'name' => 'Nowoczesny Ciemny',
            'description' => 'Elegancki ciemny motyw z subtelnymi cieniami',
            'styles' => [
                'bgcolor' => '#1F2937',
                'border_color' => '#374151',
                'text_color' => '#F9FAFB',
                'field_bgcolor' => '#374151',
                'field_text' => '#F9FAFB',
                'field_bgcolor_active' => '#4B5563',
                'field_text_active' => '#FFFFFF',
                'field_border_color' => '#4B5563',
                'field_border_color_active' => '#818CF8',
                'placeholder_color' => '#9CA3AF',
                'submit_color' => '#818CF8',
                'submit_hover_color' => '#6366F1',
                'submit_border_color' => '#818CF8',
                'shadow_enabled' => true,
                'shadow_color' => '#000000',
                'shadow_opacity' => 40,
                'shadow_blur' => 30,
                'shadow_y' => 15,
            ],
        ],
        'glassmorphism' => [
            'name' => 'Glassmorphism',
            'description' => 'Efekt szkła z rozmyciem i przezroczystością',
            'styles' => [
                'bgcolor' => '#FFFFFF',
                'bgcolor_opacity' => 70,
                'border_color' => '#FFFFFF',
                'border_width' => 1,
                'border_radius' => 16,
                'field_bgcolor' => '#FFFFFF',
                'field_border_color' => '#E5E7EB',
                'field_border_radius' => 10,
                'submit_color' => '#6366F1',
                'submit_border_radius' => 10,
                'shadow_enabled' => true,
                'shadow_color' => '#6366F1',
                'shadow_opacity' => 10,
                'shadow_blur' => 40,
            ],
        ],
        'minimal_light' => [
            'name' => 'Minimalistyczny',
            'description' => 'Czysty, prosty design z subtelnymi akcentami',
            'styles' => [
                'bgcolor' => '#FFFFFF',
                'border_color' => '#F3F4F6',
                'border_width' => 0,
                'border_radius' => 0,
                'padding' => 32,
                'field_border_color' => '#E5E7EB',
                'field_border_radius' => 0,
                'submit_color' => '#111827',
                'submit_hover_color' => '#1F2937',
                'submit_border_color' => '#111827',
                'submit_border_radius' => 0,
            ],
        ],
        'gradient_style' => [
            'name' => 'Gradient',
            'description' => 'Żywy gradient z nowoczesnymi akcentami',
            'styles' => [
                'bgcolor' => '#EEF2FF',
                'border_color' => '#C7D2FE',
                'border_radius' => 20,
                'field_border_radius' => 12,
                'submit_color' => '#6366F1',
                'submit_border_radius' => 12,
                'gradient_enabled' => true,
                'gradient_from' => '#6366F1',
                'gradient_to' => '#A855F7',
                'shadow_enabled' => true,
                'shadow_color' => '#6366F1',
                'shadow_opacity' => 20,
                'shadow_blur' => 25,
            ],
        ],
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

    /**
     * Get redirect settings based on form or list configuration
     */
    public function getRedirectSettings(): array
    {
        // If using custom form redirect
        if (!$this->use_list_redirect && $this->redirect_url) {
            return [
                'url' => $this->redirect_url,
                'message' => $this->success_message ?? 'Dziękujemy za zapisanie się!',
            ];
        }

        // Try to get from list settings
        $listSettings = $this->contactList->settings ?? [];
        $useDoubleOptin = $this->shouldUseDoubleOptin();

        if ($useDoubleOptin) {
            // For double opt-in, use confirmation page settings
            return [
                'url' => $listSettings['confirmation_page_url'] ?? null,
                'message' => $listSettings['confirmation_message'] ?? 'Sprawdź swoją skrzynkę email, aby potwierdzić subskrypcję.',
            ];
        } else {
            // For single opt-in, use thank you page settings
            return [
                'url' => $listSettings['thank_you_page_url'] ?? null,
                'message' => $listSettings['thank_you_message'] ?? $this->success_message ?? 'Dziękujemy za zapisanie się!',
            ];
        }
    }
}
