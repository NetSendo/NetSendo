<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_integration_id')->constrained('ai_integrations')->cascadeOnDelete();
            $table->string('model_id');       // e.g., "gpt-4o", "claude-3-opus"
            $table->string('display_name');   // Human-readable name
            $table->boolean('is_custom')->default(false); // User-added custom model
            $table->timestamps();

            $table->unique(['ai_integration_id', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
