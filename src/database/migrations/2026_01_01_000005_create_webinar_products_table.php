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
        Schema::create('webinar_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webinar_id')->constrained()->onDelete('cascade');

            // Product info
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('PLN');
            $table->decimal('original_price', 10, 2)->nullable(); // For showing discount

            // Link to existing payment products
            $table->foreignId('stripe_product_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('polar_product_id')->nullable()->constrained()->onDelete('set null');
            $table->string('external_checkout_url')->nullable(); // External payment link

            // Display
            $table->string('image_url')->nullable();
            $table->string('cta_text')->default('Kup teraz');
            $table->string('cta_color')->default('#6366f1');
            $table->string('cta_text_color')->default('#ffffff');

            // Timing for auto-webinars
            $table->unsignedInteger('pin_at_seconds')->nullable();
            $table->unsignedInteger('unpin_at_seconds')->nullable();

            // Countdown / urgency
            $table->boolean('show_countdown')->default(false);
            $table->unsignedInteger('countdown_minutes')->nullable();
            $table->unsignedInteger('limited_quantity')->nullable();
            $table->unsignedInteger('sold_count')->default(0);

            // Bonus items (JSON array)
            $table->json('bonuses')->nullable();
            // [{ name, description, value }]

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('pinned_at')->nullable();

            // Order for display
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            // Indexes
            $table->index(['webinar_id', 'is_active']);
            $table->index(['webinar_id', 'is_pinned']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webinar_products');
    }
};
