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
        Schema::create('sales_funnels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();

            // Thank you page - can be external_page or custom URL
            $table->foreignId('thank_you_page_id')->nullable()->constrained('external_pages')->onDelete('set null');
            $table->string('thank_you_url')->nullable(); // Alternative: custom URL

            // List to subscribe after purchase
            $table->foreignId('target_list_id')->nullable()->constrained('contact_lists')->onDelete('set null');

            // Tag to add after purchase
            $table->string('purchase_tag')->nullable();

            // Embed settings (button color, text, styles)
            $table->json('embed_settings')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        // Add sales_funnel_id to stripe_products
        Schema::table('stripe_products', function (Blueprint $table) {
            $table->foreignId('sales_funnel_id')->nullable()->after('metadata')
                ->constrained('sales_funnels')->onDelete('set null');
        });

        // Add sales_funnel_id to polar_products
        Schema::table('polar_products', function (Blueprint $table) {
            $table->foreignId('sales_funnel_id')->nullable()->after('metadata')
                ->constrained('sales_funnels')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stripe_products', function (Blueprint $table) {
            $table->dropForeign(['sales_funnel_id']);
            $table->dropColumn('sales_funnel_id');
        });

        Schema::table('polar_products', function (Blueprint $table) {
            $table->dropForeign(['sales_funnel_id']);
            $table->dropColumn('sales_funnel_id');
        });

        Schema::dropIfExists('sales_funnels');
    }
};
