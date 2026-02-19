<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_brain_settings', function (Blueprint $table) {
            $table->text('perplexity_api_key')->nullable()->after('telegram_bot_token');
            $table->text('serpapi_api_key')->nullable()->after('perplexity_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('ai_brain_settings', function (Blueprint $table) {
            $table->dropColumn(['perplexity_api_key', 'serpapi_api_key']);
        });
    }
};
