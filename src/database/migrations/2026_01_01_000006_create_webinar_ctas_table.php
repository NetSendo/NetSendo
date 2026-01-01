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
        Schema::create('webinar_ctas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webinar_id')->constrained()->onDelete('cascade');

            // CTA type
            $table->enum('type', ['button', 'countdown', 'banner', 'popup', 'sticky_bar'])->default('button');

            // Content
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('button_text')->nullable();
            $table->string('button_url')->nullable();

            // Link to product (optional)
            $table->foreignId('webinar_product_id')->nullable()->constrained()->onDelete('set null');

            // Timing
            $table->unsignedInteger('show_at_seconds')->nullable();
            $table->unsignedInteger('hide_at_seconds')->nullable();
            $table->boolean('show_permanently')->default(false);

            // Countdown settings
            $table->timestamp('countdown_to')->nullable();
            $table->unsignedInteger('countdown_seconds')->nullable(); // Countdown from X seconds
            $table->string('countdown_expired_text')->nullable();

            // Styling (JSON)
            $table->json('style')->nullable();
            // {
            //   position: 'top' | 'bottom' | 'overlay' | 'sidebar',
            //   background_color, text_color, button_color, button_text_color,
            //   animation: 'fade' | 'slide' | 'bounce',
            //   size: 'small' | 'medium' | 'large'
            // }

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_visible')->default(false); // Currently showing

            // Stats
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('clicks_count')->default(0);

            // Order
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            // Indexes
            $table->index(['webinar_id', 'is_active']);
            $table->index(['show_at_seconds']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webinar_ctas');
    }
};
