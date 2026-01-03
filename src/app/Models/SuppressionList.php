<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

/**
 * Suppression List Model
 *
 * Stores emails of users who have exercised their GDPR "right to be forgotten".
 * When a suppressed user re-subscribes, we remove them from suppression and log the event.
 *
 * @property int $id
 * @property int $user_id
 * @property string $email
 * @property string $reason
 * @property \Carbon\Carbon $suppressed_at
 */
class SuppressionList extends Model
{
    use HasFactory;

    protected $table = 'suppression_list';

    protected $fillable = [
        'user_id',
        'email',
        'reason',
        'suppressed_at',
    ];

    protected $casts = [
        'suppressed_at' => 'datetime',
    ];

    /**
     * Get the user that owns this suppression entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if an email is suppressed for a given user.
     */
    public static function isSuppressed(int $userId, string $email): bool
    {
        return static::where('user_id', $userId)
            ->where('email', strtolower($email))
            ->exists();
    }

    /**
     * Add an email to the suppression list.
     */
    public static function suppress(int $userId, string $email, string $reason = 'gdpr_erasure'): static
    {
        return static::updateOrCreate(
            [
                'user_id' => $userId,
                'email' => strtolower($email),
            ],
            [
                'reason' => $reason,
                'suppressed_at' => now(),
            ]
        );
    }

    /**
     * Handle re-subscription for a previously suppressed email.
     * Removes from suppression list and logs the event.
     * Returns true if the email was previously suppressed (and is now allowed).
     */
    public static function handleResubscription(int $userId, string $email, string $source = 'form'): bool
    {
        $email = strtolower($email);

        $suppression = static::where('user_id', $userId)
            ->where('email', $email)
            ->first();

        if (!$suppression) {
            return false; // Was not suppressed
        }

        // Log the re-subscription attempt
        Log::info('Previously suppressed email re-subscribed (GDPR consent renewed)', [
            'user_id' => $userId,
            'email' => $email,
            'original_suppression_reason' => $suppression->reason,
            'original_suppression_date' => $suppression->suppressed_at->toISOString(),
            'resubscription_source' => $source,
            'resubscription_date' => now()->toISOString(),
        ]);

        // Remove from suppression list
        $suppression->delete();

        return true; // Was suppressed, now removed
    }

    /**
     * Remove an email from suppression list without logging.
     */
    public static function unsuppress(int $userId, string $email): bool
    {
        return static::where('user_id', $userId)
            ->where('email', strtolower($email))
            ->delete() > 0;
    }
}
