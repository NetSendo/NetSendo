<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anchor autoresponder queue entries to an explicit send time instead of
     * recomputing from the list pivot's subscribed_at. This prevents the whole
     * sequence from being sent at once ("burst") when a subscriber re-signs up
     * and the pivot date is kept (resubscription_behavior = keep_date) or was
     * not reset by the signup path.
     */
    public function up(): void
    {
        Schema::table('message_queue_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('message_queue_entries', 'scheduled_for')) {
                $table->timestamp('scheduled_for')->nullable()->after('planned_at');
                $table->index(['status', 'scheduled_for']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('message_queue_entries', function (Blueprint $table) {
            if (Schema::hasColumn('message_queue_entries', 'scheduled_for')) {
                $table->dropIndex(['status', 'scheduled_for']);
                $table->dropColumn('scheduled_for');
            }
        });
    }
};
