<?php

namespace App\Console\Commands;

use App\Services\Brain\Telegram\TelegramBotService;
use Illuminate\Console\Command;

/**
 * The Telegram webhook accepts only updates carrying the secret token NetSendo gives Telegram
 * in setWebhook. Bots whose webhook was set before that (it points at this instance, without
 * the token) are set again here, so they keep working after an update. Cheap to repeat: a bot
 * done once is remembered until its token or the APP_KEY changes.
 */
class SecureTelegramWebhooksCommand extends Command
{
    protected $signature = 'brain:secure-telegram-webhooks';

    protected $description = 'Register Telegram bot webhooks with the secret token the webhook checks';

    public function handle(TelegramBotService $bot): int
    {
        $counts = $bot->secureWebhooks();

        $this->info("Telegram webhooks secured: {$counts['secured']}, left as they are: {$counts['skipped']}, failed: {$counts['failed']}.");

        return self::SUCCESS;
    }
}
