<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - automation_inactivity_marks: which "subscriber_inactive" rule already ran
 *   for which subscriber in the current inactive spell.
 * - Subscriber engagement columns (opens_count, clicks_count, last_opened_at,
 *   last_clicked_at) were never written by open/click tracking, so the
 *   subscriber card, list engagement, exports and filters read 0 for everyone.
 *   They are rebuilt from email_opens / email_clicks.
 * - "list_join" and "subscriber_activated" rules never ran: nothing raises
 *   them. Both mean what "subscriber_signup" does (it fires on joining a list,
 *   after double opt-in confirmation), so such rules become signup rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('automation_inactivity_marks')) {
            Schema::create('automation_inactivity_marks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subscriber_id')->constrained()->cascadeOnDelete();
                $table->timestamp('fired_at')->nullable();

                $table->unique(['automation_rule_id', 'subscriber_id'], 'inactivity_marks_rule_subscriber_unique');
                $table->index('subscriber_id');
            });
        }

        $this->backfill('email_opens', 'opened_at', 'opens_count', 'last_opened_at');
        $this->backfill('email_clicks', 'clicked_at', 'clicks_count', 'last_clicked_at');

        DB::table('automation_rules')
            ->whereIn('trigger_event', ['list_join', 'subscriber_activated'])
            ->update(['trigger_event' => 'subscriber_signup']);
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_inactivity_marks');
    }

    private function backfill(string $table, string $timeColumn, string $counter, string $last): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn('subscribers', $counter)) {
            return;
        }

        DB::table($table)
            ->select('subscriber_id')
            ->selectRaw('COUNT(DISTINCT message_id) as unique_messages')
            ->selectRaw("MAX({$timeColumn}) as last_at")
            ->groupBy('subscriber_id')
            ->orderBy('subscriber_id')
            ->chunk(1000, function ($rows) use ($counter, $last) {
                DB::transaction(function () use ($rows, $counter, $last) {
                    foreach ($rows as $row) {
                        DB::table('subscribers')->where('id', $row->subscriber_id)->update([
                            $counter => (int) $row->unique_messages,
                            $last => $row->last_at,
                        ]);
                    }
                });
            });
    }
};
