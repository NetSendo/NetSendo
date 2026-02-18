<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_brain_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('work_mode')->default('semi_auto'); // autonomous, semi_auto, manual
            $table->string('telegram_chat_id')->nullable();
            $table->string('telegram_username')->nullable();
            $table->string('telegram_link_code')->nullable();
            $table->timestamp('telegram_linked_at')->nullable();
            $table->string('preferred_language')->default('pl');
            $table->json('preferences')->nullable(); // AI preferences, notification settings, etc.
            $table->json('agent_permissions')->nullable(); // which agents can auto-execute
            $table->unsignedInteger('daily_token_limit')->default(100000);
            $table->unsignedInteger('tokens_used_today')->default(0);
            $table->date('token_reset_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('user_id');
            $table->index('telegram_chat_id');
            $table->index('telegram_link_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_brain_settings');
    }
};
