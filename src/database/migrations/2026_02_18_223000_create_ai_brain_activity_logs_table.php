<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_brain_activity_logs')) {
            return;
        }

        Schema::create('ai_brain_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('event_type', 50);       // 'cron_run', 'brain_start', 'brain_stop', 'agent_dispatch', 'agent_complete'
            $table->string('agent_name', 50)->nullable();
            $table->string('status', 30);            // 'started', 'completed', 'failed', 'idle'
            $table->json('metadata')->nullable();
            $table->integer('duration_ms')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'event_type']);
            $table->index('created_at');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_brain_activity_logs');
    }
};
