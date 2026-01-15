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
        Schema::create('funnel_ab_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ab_test_id')->constrained('funnel_ab_tests')->onDelete('cascade');
            $table->foreignId('variant_id')->constrained('funnel_ab_variants')->onDelete('cascade');
            $table->foreignId('funnel_subscriber_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscriber_id')->constrained()->onDelete('cascade');
            $table->boolean('converted')->default(false);
            $table->timestamp('converted_at')->nullable();
            $table->decimal('conversion_value', 12, 2)->nullable();
            $table->json('events')->nullable(); // Track opens, clicks, etc.
            $table->timestamps();

            $table->unique(['ab_test_id', 'funnel_subscriber_id']);
            $table->index(['variant_id', 'converted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_ab_enrollments');
    }
};
