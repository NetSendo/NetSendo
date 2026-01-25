<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCalendarWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class GoogleCalendarController extends Controller
{
    /**
     * Handle Google Calendar push notification webhook.
     *
     * @see https://developers.google.com/calendar/api/guides/push
     */
    public function handle(Request $request): Response
    {
        // Get headers from Google
        $channelId = $request->header('X-Goog-Channel-ID');
        $resourceId = $request->header('X-Goog-Resource-ID');
        $resourceState = $request->header('X-Goog-Resource-State');
        $messageNumber = $request->header('X-Goog-Message-Number');

        // Validate required headers
        if (!$channelId || !$resourceId || !$resourceState) {
            Log::warning('Invalid Calendar webhook: missing headers', [
                'channel_id' => $channelId,
                'resource_id' => $resourceId,
                'state' => $resourceState,
            ]);
            return response('Missing required headers', 400);
        }

        Log::info('Received Google Calendar webhook', [
            'channel_id' => $channelId,
            'resource_id' => $resourceId,
            'state' => $resourceState,
            'message_number' => $messageNumber,
        ]);

        // Dispatch job to process the webhook asynchronously
        ProcessCalendarWebhook::dispatch($channelId, $resourceId, $resourceState);

        // Return 200 OK immediately to acknowledge receipt
        // Google expects a quick response (within 10 seconds)
        return response('', 200);
    }
}
