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
            // Google Meet integration fields
            $table->string('google_meet_link')->nullable()->after('google_calendar_etag');
            $table->string('google_meet_id')->nullable()->after('google_meet_link');
            $table->boolean('include_google_meet')->default(false)->after('google_meet_id');
            $table->json('attendee_emails')->nullable()->after('include_google_meet');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table) {
            $table->dropColumn([
                'google_meet_link',
                'google_meet_id',
                'include_google_meet',
                'attendee_emails',
            ]);
        });
    }
};
