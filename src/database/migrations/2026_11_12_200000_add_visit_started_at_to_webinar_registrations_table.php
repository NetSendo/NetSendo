<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the registrant's current visit to the webinar room began. joined_at
     * keeps the first visit; watch time is counted per visit, so coming back
     * later no longer adds the time spent away.
     */
    public function up(): void
    {
        Schema::table('webinar_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('webinar_registrations', 'visit_started_at')) {
                $table->timestamp('visit_started_at')->nullable()->after('left_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('webinar_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('webinar_registrations', 'visit_started_at')) {
                $table->dropColumn('visit_started_at');
            }
        });
    }
};
