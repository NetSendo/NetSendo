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
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('campaign_plan_id')
                ->nullable()
                ->after('user_id')
                ->constrained('campaign_plans')
                ->onDelete('set null');

            $table->index('campaign_plan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['campaign_plan_id']);
            $table->dropColumn('campaign_plan_id');
        });
    }
};
