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
        Schema::create('campaign_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('overall_score')->default(0); // 0-100
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->string('audit_type')->default('full'); // quick (rules only), full (with AI)
            $table->json('summary')->nullable(); // Quick stats
            $table->json('metrics')->nullable(); // Detailed metrics
            $table->integer('critical_count')->default(0);
            $table->integer('warning_count')->default(0);
            $table->integer('info_count')->default(0);
            $table->decimal('estimated_revenue_loss', 12, 2)->nullable();
            $table->integer('messages_analyzed')->default(0);
            $table->integer('lists_analyzed')->default(0);
            $table->integer('automations_analyzed')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_audits');
    }
};
