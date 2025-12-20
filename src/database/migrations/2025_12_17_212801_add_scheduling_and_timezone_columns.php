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
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone')->nullable()->default('UTC');
        });

        Schema::table('contact_list_groups', function (Blueprint $table) {
            $table->string('timezone')->nullable();
        });

        Schema::table('contact_lists', function (Blueprint $table) {
            $table->string('timezone')->nullable();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('timezone')->nullable();
            $table->timestamp('send_at')->nullable();
            $table->time('time_of_day')->nullable(); // For autoresponders specific time
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'send_at', 'time_of_day']);
        });

        Schema::table('contact_lists', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });

        Schema::table('contact_list_groups', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
