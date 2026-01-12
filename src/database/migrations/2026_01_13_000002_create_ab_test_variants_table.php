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
        Schema::create('ab_test_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ab_test_id')->constrained()->cascadeOnDelete();
            $table->char('variant_letter', 1);
            $table->string('subject')->nullable();
            $table->string('preheader')->nullable();
            $table->longText('content')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->timestamp('scheduled_send_time')->nullable();
            $table->unsignedTinyInteger('weight')->default(100);
            $table->boolean('is_control')->default(false);
            $table->boolean('is_ai_generated')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['ab_test_id', 'variant_letter']);
            $table->index(['ab_test_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ab_test_variants');
    }
};
