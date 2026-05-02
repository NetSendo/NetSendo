<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds custom_headers JSON column to messages table for API-provided per-message headers.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->json('custom_headers')->nullable()->after('preheader');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('custom_headers');
        });
    }
};
