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
        Schema::table('pixel_events', function (Blueprint $table) {
            $table->string('session_id', 64)->nullable()->after('custom_data')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pixel_events', function (Blueprint $table) {
            $table->dropColumn('session_id');
        });
    }
};
