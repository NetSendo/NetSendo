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
        // Add trigger_conditions to sequences for advanced trigger configuration
        Schema::table('crm_follow_up_sequences', function (Blueprint $table) {
            $table->json('trigger_conditions')->nullable()->after('trigger_type');
        });

        // Add notification_preferences to users for email/SMS reminders
        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable()->after('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_follow_up_sequences', function (Blueprint $table) {
            $table->dropColumn('trigger_conditions');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
