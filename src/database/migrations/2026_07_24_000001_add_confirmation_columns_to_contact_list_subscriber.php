<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ActivationController writes confirmed_at (double opt-in confirmation) and
     * resubscribed_at (re-signup via link) to the pivot, but the columns never
     * existed — every double opt-in confirmation and resubscribe attempt failed
     * with an SQL error before this migration.
     */
    public function up(): void
    {
        Schema::table('contact_list_subscriber', function (Blueprint $table) {
            if (!Schema::hasColumn('contact_list_subscriber', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('subscribed_at');
            }
            if (!Schema::hasColumn('contact_list_subscriber', 'resubscribed_at')) {
                $table->timestamp('resubscribed_at')->nullable()->after('confirmed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contact_list_subscriber', function (Blueprint $table) {
            if (Schema::hasColumn('contact_list_subscriber', 'confirmed_at')) {
                $table->dropColumn('confirmed_at');
            }
            if (Schema::hasColumn('contact_list_subscriber', 'resubscribed_at')) {
                $table->dropColumn('resubscribed_at');
            }
        });
    }
};
