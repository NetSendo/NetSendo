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
        Schema::create('tpay_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Product details
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('tpay_product_id')->nullable(); // Optional external reference
            $table->integer('price'); // In grosze (1/100 PLN)
            $table->string('currency', 3)->default('PLN');
            $table->enum('type', ['one_time', 'subscription'])->default('one_time');
            $table->boolean('is_active')->default(true);

            // Sales funnel integration
            $table->foreignId('sales_funnel_id')->nullable()->constrained('sales_funnels')->onDelete('set null');

            // Extra configuration
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
        Schema::dropIfExists('tpay_products');
    }
};
