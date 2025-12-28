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
        Schema::create('stripe_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Stripe IDs
            $table->string('stripe_product_id')->nullable()->index();
            $table->string('stripe_price_id')->nullable()->index();

            // Product info
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('price'); // in smallest currency unit (cents/grosze)
            $table->string('currency', 3)->default('PLN');
            $table->enum('type', ['one_time', 'subscription'])->default('one_time');

            // Status
            $table->boolean('is_active')->default(true);

            // Additional data
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_products');
    }
};
