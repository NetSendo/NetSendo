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
        Schema::create('funnel_ab_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ab_test_id')->constrained('funnel_ab_tests')->onDelete('cascade');
            $table->string('name'); // "Wariant A", "Wariant B"
            $table->unsignedInteger('weight')->default(50); // Distribution percentage (0-100)
            $table->foreignId('next_step_id')->nullable()->constrained('funnel_steps')->nullOnDelete();
            $table->unsignedInteger('enrollments')->default(0);
            $table->unsignedInteger('conversions')->default(0);
            $table->unsignedInteger('opens')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('ab_test_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_ab_variants');
    }
};
