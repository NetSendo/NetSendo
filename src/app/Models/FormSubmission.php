<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_form_id',
        'subscriber_id',
        'status',
        'submission_data',
        'ip_address',
        'user_agent',
        'referrer',
        'source',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'submission_data' => 'array',
        'processed_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function form(): BelongsTo
    {
        return $this->belongsTo(SubscriptionForm::class, 'subscription_form_id');
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    // ========== SCOPES ==========

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeError($query)
    {
        return $query->where('status', 'error');
    }

    public function scopeByDate($query, $from, $to = null)
    {
        $query->where('created_at', '>=', $from);
        if ($to) {
            $query->where('created_at', '<=', $to);
        }
        return $query;
    }

    public function scopeForForm($query, $formId)
    {
        return $query->where('subscription_form_id', $formId);
    }

    // ========== HELPERS ==========

    /**
     * Mark submission as confirmed
     */
    public function markConfirmed(): void
    {
        $this->update([
            'status' => 'confirmed',
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark submission as error
     */
    public function markError(string $message): void
    {
        $this->update([
            'status' => 'error',
            'error_message' => $message,
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark submission as rejected (spam, duplicate, etc.)
     */
    public function markRejected(string $reason = null): void
    {
        $this->update([
            'status' => 'rejected',
            'error_message' => $reason,
            'processed_at' => now(),
        ]);
    }

    /**
     * Get email from submission data
     */
    public function getEmail(): ?string
    {
        return $this->submission_data['email'] ?? null;
    }

    /**
     * Check if this is a successful submission
     */
    public function isSuccessful(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }
}
