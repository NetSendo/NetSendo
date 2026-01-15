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
        Schema::table('contact_list_subscriber', function (Blueprint $table) {
            if (!Schema::hasColumn('contact_list_subscriber', 'soft_bounce_count')) {
                $table->unsignedInteger('soft_bounce_count')->default(0)->after('unsubscribed_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_list_subscriber', function (Blueprint $table) {
            if (Schema::hasColumn('contact_list_subscriber', 'soft_bounce_count')) {
                $table->dropColumn('soft_bounce_count');
            }
        });
    }
};
