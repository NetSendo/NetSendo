<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_brain_settings', function (Blueprint $table) {
            $table->text('telegram_bot_token')->nullable()->after('telegram_linked_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_brain_settings', function (Blueprint $table) {
            $table->dropColumn('telegram_bot_token');
        });
    }
};
