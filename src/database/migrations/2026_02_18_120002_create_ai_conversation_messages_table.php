<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->onDelete('cascade');
            $table->string('role'); // user, assistant, system, tool
            $table->text('content');
            $table->json('metadata')->nullable(); // intent, confidence, agent used
            $table->json('tool_calls')->nullable(); // actions dispatched by AI
            $table->json('tool_results')->nullable(); // results from tool calls
            $table->unsignedInteger('tokens_input')->default(0);
            $table->unsignedInteger('tokens_output')->default(0);
            $table->string('model_used')->nullable();
            $table->timestamps();

            $table->index('ai_conversation_id');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversation_messages');
    }
};
