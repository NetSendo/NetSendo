<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table) {
            // Google Calendar sync fields
            $table->string('google_calendar_event_id')->nullable()->after('follow_up_enrollment_id');
            $table->string('google_calendar_id')->nullable()->after('google_calendar_event_id');
            $table->timestamp('google_calendar_synced_at')->nullable()->after('google_calendar_id');
            $table->boolean('sync_to_calendar')->default(false)->after('google_calendar_synced_at');

            // Index for efficient lookups
            $table->index('google_calendar_event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table) {
            $table->dropIndex(['google_calendar_event_id']);

            $table->dropColumn([
                'google_calendar_event_id',
                'google_calendar_id',
                'google_calendar_synced_at',
                'sync_to_calendar',
            ]);
        });
    }
};
