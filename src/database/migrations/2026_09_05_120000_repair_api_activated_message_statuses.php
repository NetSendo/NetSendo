<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bring messages activated through the public API back to a status the
     * send pipeline understands.
     *
     * `messages.status` only ever means draft | scheduled | sent, but the API
     * activation endpoint wrote 'active' for autoresponders and 'sending' for
     * broadcasts. Both the signup listener and the cron processor filter on
     * `status = 'scheduled'`, so those campaigns were invisible to them: an
     * autoresponder created no queue entries at all (every subscriber ending up
     * as "skipped" in the UI) and a broadcast kept its planned entries but was
     * never dispatched.
     *
     * Autoresponders are restored to 'scheduled' so they resume sending.
     * Broadcasts are NOT auto-dispatched — one could be weeks old and blasting
     * it on deploy is not a decision a migration should make. They go back to
     * 'draft' (already how the UI rendered them) if nothing went out, or 'sent'
     * if delivery had partially happened, leaving the owner in control.
     */
    public function up(): void
    {
        if (!Schema::hasTable('messages')) {
            return;
        }

        $stranded = DB::table('messages')
            ->whereIn('status', ['active', 'sending'])
            ->get(['id', 'type', 'status', 'sent_count', 'scheduled_at', 'created_at']);

        foreach ($stranded as $message) {
            $isQueueType = $message->type === 'autoresponder';

            if ($isQueueType) {
                $update = [
                    'status' => 'scheduled',
                    'is_active' => true,
                    'scheduled_at' => $message->scheduled_at ?: $message->created_at,
                ];
            } else {
                $update = [
                    'status' => (int) ($message->sent_count ?? 0) > 0 ? 'sent' : 'draft',
                ];
            }

            DB::table('messages')->where('id', $message->id)->update($update);

            Log::info('Repaired message status written by the API activation endpoint', [
                'message_id' => $message->id,
                'type' => $message->type,
                'from' => $message->status,
                'to' => $update['status'],
            ]);
        }
    }

    /**
     * Irreversible: 'active' and 'sending' were never valid statuses, so there
     * is no earlier state worth restoring.
     */
    public function down(): void
    {
        // no-op
    }
};
