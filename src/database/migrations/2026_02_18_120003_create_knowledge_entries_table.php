<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('category'); // company, products, brand_voice, best_practices, insights, audience
            $table->string('title');
            $table->text('content');
            $table->text('content_embedding')->nullable(); // for vector similarity search
            $table->string('source')->default('user'); // user, ai_enrichment, campaign_analysis, conversation
            $table->string('source_reference')->nullable(); // conversation_id, campaign_id, etc.
            $table->json('tags')->nullable();
            $table->float('confidence')->default(1.0); // 0-1, lower for AI-generated
            $table->boolean('is_verified')->default(false); // user-verified flag
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usage_count')->default(0); // how often used in AI context
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'category']);
            $table->index(['user_id', 'is_active']);
            $table->fullText(['title', 'content']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_entries');
    }
};
