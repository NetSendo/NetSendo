<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class CardIntelSettings extends Model
{
    protected $table = 'cardintel_settings';

    protected $fillable = [
        'user_id',
        'default_mode',
        'low_threshold',
        'high_threshold',
        'crm_sync_mode',
        'crm_min_score',
        'default_email_lists',
        'default_sms_lists',
        'list_add_mode',
        'enrichment_enabled',
        'enrichment_only_medium_high',
        'enrichment_timeout',
        'auto_send_enabled',
        'auto_send_min_score',
        'auto_send_corporate_only',
        'default_tone',
        'show_all_context_levels',
        'default_mailbox_id',
        'custom_ai_prompt',
        'allowed_html_tags',
    ];

    protected $casts = [
        'default_email_lists' => 'array',
        'default_sms_lists' => 'array',
        'enrichment_enabled' => 'boolean',
        'enrichment_only_medium_high' => 'boolean',
        'auto_send_enabled' => 'boolean',
        'auto_send_corporate_only' => 'boolean',
        'show_all_context_levels' => 'boolean',
        'low_threshold' => 'integer',
        'high_threshold' => 'integer',
        'crm_min_score' => 'integer',
        'enrichment_timeout' => 'integer',
        'auto_send_min_score' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Mode constants.
     */
    public const MODE_MANUAL = 'manual';
    public const MODE_AGENT = 'agent';
    public const MODE_AUTO = 'auto';

    /**
     * CRM sync mode constants.
     */
    public const CRM_SYNC_ALWAYS = 'always';
    public const CRM_SYNC_APPROVE = 'approve';
    public const CRM_SYNC_HIGH_ONLY = 'high_only';
    public const CRM_SYNC_NEVER = 'never';

    /**
     * List add mode constants.
     */
    public const LIST_ADD_ALWAYS = 'always';
    public const LIST_ADD_APPROVE = 'approve';
    public const LIST_ADD_HIGH_ONLY = 'high_only';

    /**
     * Tone constants.
     */
    public const TONE_PROFESSIONAL = 'professional';
    public const TONE_FRIENDLY = 'friendly';
    public const TONE_FORMAL = 'formal';

    /**
     * Default settings values.
     */
    public const DEFAULTS = [
        'default_mode' => self::MODE_MANUAL,
        'low_threshold' => 50,
        'high_threshold' => 80,
        'crm_sync_mode' => self::CRM_SYNC_APPROVE,
        'crm_min_score' => 80,
        'default_email_lists' => [],
        'default_sms_lists' => [],
        'list_add_mode' => self::LIST_ADD_APPROVE,
        'enrichment_enabled' => true,
        'enrichment_only_medium_high' => true,
        'enrichment_timeout' => 10,
        'auto_send_enabled' => false,
        'auto_send_min_score' => 80,
        'auto_send_corporate_only' => true,
        'default_tone' => self::TONE_PROFESSIONAL,
        'show_all_context_levels' => true,
        'allowed_html_tags' => 'p,br,strong,em,ul,ol,li,a,h3,h4',
    ];

    /**
     * Get the user that owns these settings.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the default mailbox for sending messages.
     */
    public function defaultMailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class, 'default_mailbox_id');
    }

    /**
     * Get or create settings for a user.
     */
    public static function getForUser(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            self::DEFAULTS
        );
    }

    /**
     * Check if mode is manual.
     */
    public function isManualMode(): bool
    {
        return $this->default_mode === self::MODE_MANUAL;
    }

    /**
     * Check if mode is agent.
     */
    public function isAgentMode(): bool
    {
        return $this->default_mode === self::MODE_AGENT;
    }

    /**
     * Check if mode is auto.
     */
    public function isAutoMode(): bool
    {
        return $this->default_mode === self::MODE_AUTO;
    }

    /**
     * Determine context level based on score.
     */
    public function getContextLevel(int $score): string
    {
        if ($score >= $this->high_threshold) {
            return CardIntelContext::LEVEL_HIGH;
        }

        if ($score >= $this->low_threshold) {
            return CardIntelContext::LEVEL_MEDIUM;
        }

        return CardIntelContext::LEVEL_LOW;
    }

    /**
     * Check if should auto-sync to CRM.
     */
    public function shouldAutoSyncToCrm(int $score): bool
    {
        return match($this->crm_sync_mode) {
            self::CRM_SYNC_ALWAYS => true,
            self::CRM_SYNC_HIGH_ONLY => $score >= $this->crm_min_score,
            default => false, // approve, never
        };
    }

    /**
     * Check if should auto-add to lists.
     */
    public function shouldAutoAddToLists(int $score): bool
    {
        return match($this->list_add_mode) {
            self::LIST_ADD_ALWAYS => true,
            self::LIST_ADD_HIGH_ONLY => $score >= $this->high_threshold,
            default => false, // approve
        };
    }

    /**
     * Check if should run enrichment for a context level.
     */
    public function shouldEnrich(string $contextLevel): bool
    {
        if (!$this->enrichment_enabled) {
            return false;
        }

        if (!$this->enrichment_only_medium_high) {
            return true;
        }

        return in_array($contextLevel, [
            CardIntelContext::LEVEL_MEDIUM,
            CardIntelContext::LEVEL_HIGH,
        ]);
    }

    /**
     * Check if qualifies for auto-send.
     */
    public function qualifiesForAutoSend(CardIntelContext $context): bool
    {
        if (!$this->auto_send_enabled) {
            return false;
        }

        if (!$context->isHigh()) {
            return false;
        }

        if ($context->quality_score < $this->auto_send_min_score) {
            return false;
        }

        if ($this->auto_send_corporate_only && !($context->signals['corporate_email'] ?? false)) {
            return false;
        }

        return true;
    }

    /**
     * Get available modes for dropdown.
     */
    public static function getAvailableModes(): array
    {
        return [
            ['value' => self::MODE_MANUAL, 'label' => 'Ręczny (Manual Review)'],
            ['value' => self::MODE_AGENT, 'label' => 'Agent (Semi-Auto)'],
            ['value' => self::MODE_AUTO, 'label' => 'Auto (Pełna automatyzacja)'],
        ];
    }

    /**
     * Get available CRM sync modes for dropdown.
     */
    public static function getAvailableCrmSyncModes(): array
    {
        return [
            ['value' => self::CRM_SYNC_ALWAYS, 'label' => 'Zawsze'],
            ['value' => self::CRM_SYNC_APPROVE, 'label' => 'Po zatwierdzeniu'],
            ['value' => self::CRM_SYNC_HIGH_ONLY, 'label' => 'Tylko HIGH + min. score'],
            ['value' => self::CRM_SYNC_NEVER, 'label' => 'Nigdy'],
        ];
    }

    /**
     * Get available tones for dropdown.
     */
    public static function getAvailableTones(): array
    {
        return [
            ['value' => self::TONE_PROFESSIONAL, 'label' => 'Profesjonalny'],
            ['value' => self::TONE_FRIENDLY, 'label' => 'Przyjazny'],
            ['value' => self::TONE_FORMAL, 'label' => 'Formalny'],
        ];
    }
}
