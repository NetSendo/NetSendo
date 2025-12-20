<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // openai, anthropic, grok, openrouter, ollama, gemini
            $table->string('name');     // User-friendly name for the integration
            $table->text('api_key')->nullable(); // Encrypted API key (nullable for Ollama)
            $table->string('base_url')->nullable(); // Custom endpoint (for Ollama, custom deployments)
            $table->string('default_model')->nullable(); // Default model to use
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable(); // success, error
            $table->text('last_test_message')->nullable(); // Error message or success info
            $table->timestamps();

            $table->index('provider');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_integrations');
    }
};
