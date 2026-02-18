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
