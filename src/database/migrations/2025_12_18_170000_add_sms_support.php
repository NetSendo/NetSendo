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
        // Contact Lists Table
        Schema::table('contact_lists', function (Blueprint $table) {
            $table->string('type')->default('email')->after('name'); // email, sms
        });

        // Subscribers Table
        Schema::table('subscribers', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
        });

        // Messages Table
        Schema::table('messages', function (Blueprint $table) {
            $table->string('channel')->default('email')->after('user_id'); // email, sms
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_lists', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn('phone');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }
};
