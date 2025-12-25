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
        Schema::table('campaign_plans', function (Blueprint $table) {
            $table->string('campaign_language', 5)->default('en')->after('campaign_goal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_plans', function (Blueprint $table) {
            $table->dropColumn('campaign_language');
        });
    }
};
