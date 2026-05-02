<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_brain_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_brain_settings', 'strategy_settings')) {
                $table->json('strategy_settings')->nullable()->after('agent_permissions');
            }
            if (!Schema::hasColumn('ai_brain_settings', 'agent_modes')) {
                $table->json('agent_modes')->nullable()->after('strategy_settings');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_brain_settings', function (Blueprint $table) {
            $table->dropColumn(['strategy_settings', 'agent_modes']);
        });
    }
};
