<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_pending_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_action_plan_id')->constrained('ai_action_plans')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('channel'); // telegram, web, api
            $table->string('status')->default('pending'); // pending, approved, rejected, expired
            $table->text('summary'); // human-readable plan summary for the user
            $table->json('approval_options')->nullable(); // custom approval options
            $table->string('telegram_message_id')->nullable(); // for inline keyboard callback
            $table->text('rejection_reason')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_pending_approvals');
    }
};
