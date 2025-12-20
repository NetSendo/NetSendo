<?php

namespace App\Http\Controllers;

use App\Models\EmailClick;
use App\Models\EmailOpen;
use App\Events\EmailOpened;
use App\Events\EmailClicked;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TrackingController extends Controller
{
    public function trackOpen($messageId, $subscriberId, $hash, Request $request)
    {
        if (!$this->verifyHash($messageId, $subscriberId, $hash)) {
            abort(403);
        }

        try {
            EmailOpen::create([
                'message_id' => $messageId,
                'subscriber_id' => $subscriberId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'opened_at' => now(),
            ]);

            // Dispatch event for automations
            event(new EmailOpened(
                (int) $messageId,
                (int) $subscriberId,
                $request->ip(),
                $request->userAgent()
            ));
        } catch (\Exception $e) {
            Log::error('Failed to track open: ' . $e->getMessage());
        }

        // Return 1x1 transparent GIF
        $content = base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
        
        return response($content)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    public function trackClick($messageId, $subscriberId, $hash, Request $request)
    {
        if (!$this->verifyHash($messageId, $subscriberId, $hash)) {
            abort(403);
        }

        $url = $request->query('url');

        if (!$url) {
            abort(404);
        }

        // Decode URL if it was encoded (it should be allowed in query params, but just in case)
        // Usually, the browser handles decoding, but if we encoded it in the email link, we get the raw URL.
        
        try {
            EmailClick::create([
                'message_id' => $messageId,
                'subscriber_id' => $subscriberId,
                'url' => $url,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'clicked_at' => now(),
            ]);

            // Dispatch event for automations
            event(new EmailClicked(
                (int) $messageId,
                (int) $subscriberId,
                $url,
                $request->ip(),
                $request->userAgent()
            ));
        } catch (\Exception $e) {
            Log::error('Failed to track click: ' . $e->getMessage());
        }

        return redirect()->away($url);
    }

    private function verifyHash($messageId, $subscriberId, $hash)
    {
        $expectedHash = hash_hmac('sha256', "{$messageId}.{$subscriberId}", config('app.key'));
        return hash_equals($expectedHash, $hash);
    }
}
