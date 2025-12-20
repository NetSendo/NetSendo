<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('template_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // header, text, image, button, product, columns, social, footer, etc.
            $table->json('content'); // Block content in JSON format
            $table->json('settings')->nullable(); // Block style settings
            $table->string('thumbnail')->nullable(); // Preview thumbnail
            $table->boolean('is_global')->default(false); // Available publicly
            $table->integer('usage_count')->default(0); // Track usage
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index('is_global');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_blocks');
    }
};
