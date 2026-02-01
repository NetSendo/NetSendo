<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class CardIntelAction extends Model
{
    protected $table = 'cardintel_actions';

    protected $fillable = [
        'scan_id',
        'action_type',
        'status',
        'payload_json',
        'error_message',
        'executed_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'executed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Action type constants.
     */
    public const TYPE_SAVE_MEMORY = 'save_memory';
    public const TYPE_ADD_CRM = 'add_crm';
    public const TYPE_ADD_EMAIL_LIST = 'add_email_list';
    public const TYPE_ADD_SMS_LIST = 'add_sms_list';
    public const TYPE_SEND_EMAIL = 'send_email';
    public const TYPE_SEND_SMS = 'send_sms';
    public const TYPE_GENERATE_MESSAGE = 'generate_message';

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /**
     * Get the scan this action belongs to.
     */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(CardIntelScan::class, 'scan_id');
    }

    /**
     * Get payload as array (accessor).
     */
    protected function payload(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->payload_json ?? [],
        );
    }

    /**
     * Scope for completed actions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for pending actions.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for failed actions.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for specific action type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('action_type', $type);
    }

    /**
     * Check if action is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if action is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if action failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Mark action as completed.
     */
    public function markAsCompleted(array $payload = []): self
    {
        $existingPayload = $this->payload_json ?? [];

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'payload_json' => array_merge($existingPayload, $payload),
            'executed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark action as failed.
     */
    public function markAsFailed(string $errorMessage): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'executed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Get human-readable action type label (Polish).
     */
    public function getTypeLabelAttribute(): string
    {
        return match($this->action_type) {
            self::TYPE_SAVE_MEMORY => 'Zapisz do pamięci',
            self::TYPE_ADD_CRM => 'Dodaj do CRM',
            self::TYPE_ADD_EMAIL_LIST => 'Dodaj do listy email',
            self::TYPE_ADD_SMS_LIST => 'Dodaj do listy SMS',
            self::TYPE_SEND_EMAIL => 'Wyślij email',
            self::TYPE_SEND_SMS => 'Wyślij SMS',
            self::TYPE_GENERATE_MESSAGE => 'Wygeneruj wiadomość',
            default => $this->action_type,
        };
    }

    /**
     * Get status label (Polish).
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Oczekuje',
            self::STATUS_COMPLETED => 'Zakończone',
            self::STATUS_FAILED => 'Błąd',
            default => $this->status,
        };
    }

    /**
     * Get status color for UI.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_FAILED => 'red',
            default => 'gray',
        };
    }

    /**
     * Create a new action for a scan.
     */
    public static function createForScan(
        CardIntelScan $scan,
        string $actionType,
        array $payload = []
    ): self {
        return self::create([
            'scan_id' => $scan->id,
            'action_type' => $actionType,
            'status' => self::STATUS_PENDING,
            'payload_json' => $payload,
        ]);
    }
}
