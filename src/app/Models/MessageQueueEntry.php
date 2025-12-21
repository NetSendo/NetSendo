<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageQueueEntry extends Model
{
    const STATUS_PLANNED = 'planned';
    const STATUS_QUEUED = 'queued';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'message_id',
        'subscriber_id',
        'status',
        'planned_at',
        'queued_at',
        'sent_at',
        'error_message',
    ];

    protected $casts = [
        'planned_at' => 'datetime',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /**
     * Get the message this entry belongs to.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get the subscriber this entry is for.
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    /**
     * Mark as queued (ready to send).
     */
    public function markAsQueued(): self
    {
        $this->update([
            'status' => self::STATUS_QUEUED,
            'queued_at' => now(),
        ]);
        return $this;
    }

    /**
     * Mark as sent successfully.
     */
    public function markAsSent(): self
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
        ]);
        return $this;
    }

    /**
     * Mark as failed with error message.
     */
    public function markAsFailed(string $errorMessage): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
        return $this;
    }

    /**
     * Mark as skipped (e.g., subscriber unsubscribed).
     */
    public function markAsSkipped(string $reason = null): self
    {
        $this->update([
            'status' => self::STATUS_SKIPPED,
            'error_message' => $reason,
        ]);
        return $this;
    }

    /**
     * Scope for pending entries (planned or queued).
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_PLANNED, self::STATUS_QUEUED]);
    }

    /**
     * Scope for entries ready to send.
     */
    public function scopeReadyToSend($query)
    {
        return $query->where('status', self::STATUS_QUEUED);
    }

    /**
     * Scope for planned entries.
     */
    public function scopePlanned($query)
    {
        return $query->where('status', self::STATUS_PLANNED);
    }
}
