<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration:
     * 1. Changes default timezone from 'Europe/Warsaw' to nullable (inherit from user)
     * 2. Updates existing webinars to use owner's timezone
     * 3. Updates AutoWebinarSchedule to inherit from webinar
     */
    public function up(): void
    {
        // Make timezone nullable first
        Schema::table('webinars', function (Blueprint $table) {
            $table->string('timezone')->nullable()->change();
        });

        Schema::table('auto_webinar_schedules', function (Blueprint $table) {
            $table->string('timezone')->nullable()->change();
        });

        // Update webinars to inherit from owner (NULL) where currently Europe/Warsaw (default)
        DB::statement("
            UPDATE webinars
            SET timezone = NULL
            WHERE timezone = 'Europe/Warsaw'
        ");

        // Update auto_webinar_schedules to inherit from webinar (NULL)
        DB::statement("
            UPDATE auto_webinar_schedules
            SET timezone = NULL
            WHERE timezone = 'Europe/Warsaw'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to Europe/Warsaw default
        DB::table('webinars')
            ->whereNull('timezone')
            ->update(['timezone' => 'Europe/Warsaw']);

        DB::table('auto_webinar_schedules')
            ->whereNull('timezone')
            ->update(['timezone' => 'Europe/Warsaw']);

        Schema::table('webinars', function (Blueprint $table) {
            $table->string('timezone')->default('Europe/Warsaw')->nullable(false)->change();
        });

        Schema::table('auto_webinar_schedules', function (Blueprint $table) {
            $table->string('timezone')->default('Europe/Warsaw')->nullable(false)->change();
        });
    }
};
