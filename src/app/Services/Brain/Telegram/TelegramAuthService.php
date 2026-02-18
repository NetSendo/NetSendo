<?php

namespace App\Services\Brain\Telegram;

use App\Models\AiBrainSettings;
use App\Models\User;
use Illuminate\Support\Str;

class TelegramAuthService
{
    /**
     * Generate a link code for connecting Telegram to a NetSendo account.
     */
    public function generateLinkCode(User $user): string
    {
        $settings = AiBrainSettings::getForUser($user->id);
        $code = Str::upper(Str::random(8));

        $settings->update([
            'telegram_link_code' => $code,
        ]);

        return $code;
    }

    /**
     * Attempt to link a Telegram chat to a NetSendo account using a link code.
     */
    public function linkAccount(string $code, string $chatId, ?string $username = null): ?User
    {
        $settings = AiBrainSettings::where('telegram_link_code', $code)->first();

        if (!$settings) {
            return null;
        }

        $settings->update([
            'telegram_chat_id' => $chatId,
            'telegram_username' => $username,
            'telegram_linked_at' => now(),
            'telegram_link_code' => null, // Clear the code after use
        ]);

        return $settings->user;
    }

    /**
     * Unlink Telegram from a user account.
     */
    public function unlinkAccount(User $user): void
    {
        $settings = AiBrainSettings::getForUser($user->id);

        $settings->update([
            'telegram_chat_id' => null,
            'telegram_username' => null,
            'telegram_linked_at' => null,
        ]);
    }

    /**
     * Find a user by their Telegram chat ID.
     */
    public function findUserByChatId(string $chatId): ?User
    {
        $settings = AiBrainSettings::where('telegram_chat_id', $chatId)->first();

        return $settings?->user;
    }

    /**
     * Check if a chat ID is linked to any account.
     */
    public function isLinked(string $chatId): bool
    {
        return AiBrainSettings::where('telegram_chat_id', $chatId)->exists();
    }
}
