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
        Schema::create('funnel_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category'); // welcome, reengagement, launch, cart_abandonment, webinar
            $table->json('structure'); // Complete funnel definition (nodes, edges, settings)
            $table->string('thumbnail')->nullable();
            $table->boolean('is_public')->default(false); // System templates are public
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('uses_count')->default(0);
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->index(['category', 'is_public']);
            $table->index('is_featured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_templates');
    }
};
