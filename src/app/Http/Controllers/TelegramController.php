<?php

namespace App\Http\Controllers;

use App\Services\Brain\Telegram\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    public function __construct(
        protected TelegramBotService $botService,
    ) {}

    /**
     * Handle incoming Telegram webhook.
     * POST /api/telegram/webhook
     */
    public function webhook(Request $request): JsonResponse
    {
        $update = $request->all();

        // The URL is public: only the secret token given to Telegram in setWebhook proves an
        // update is real. A webhook set before the token existed is set again by the scheduler
        // (brain:secure-telegram-webhooks); Telegram retries refused updates until then.
        try {
            $authentic = $this->botService->isAuthenticUpdate($update, $request->header(TelegramBotService::SECRET_HEADER));
        } catch (\Throwable $e) {
            Log::error('Telegram webhook check failed', ['error' => $e->getMessage()]);
            $authentic = false;
        }

        if (!$authentic) {
            Log::warning('Telegram webhook refused: missing or wrong secret token', ['update_id' => $update['update_id'] ?? null]);

            return response()->json(['ok' => false], 403);
        }

        Log::debug('Telegram webhook received', ['update_id' => $update['update_id'] ?? null]);

        try {
            $this->botService->processUpdate($update);
        } catch (\Exception $e) {
            Log::error('Telegram webhook error', ['error' => $e->getMessage()]);
        }

        // Always return 200 to Telegram
        return response()->json(['ok' => true]);
    }

    /**
     * Set the webhook URL for the Telegram bot.
     * POST /api/brain/telegram/set-webhook
     */
    public function setWebhook(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url',
        ]);

        $result = $this->botService->setWebhook($request->input('url'), $request->user());

        return response()->json($result);
    }
}
