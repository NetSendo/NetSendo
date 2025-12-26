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
        Schema::create('campaign_audit_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_audit_id')->constrained()->cascadeOnDelete();
            $table->string('severity'); // critical, warning, info
            $table->string('category'); // frequency, content, timing, segmentation, deliverability, revenue
            $table->string('issue_key'); // Unique key for translation (e.g., over_mailing)
            $table->text('message'); // AI-generated or rule-based message
            $table->text('recommendation')->nullable(); // Suggested fix
            $table->decimal('impact_score', 5, 2)->nullable(); // Impact on revenue/engagement (0-100)
            $table->string('affected_type')->nullable(); // message, automation, contact_list
            $table->unsignedBigInteger('affected_id')->nullable(); // ID of affected item
            $table->json('context')->nullable(); // Extra data for fixes (suggested_action, thresholds, etc.)
            $table->boolean('is_fixable')->default(false); // Can be auto-fixed
            $table->boolean('is_fixed')->default(false); // Has been fixed
            $table->timestamp('fixed_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_audit_id', 'severity']);
            $table->index(['campaign_audit_id', 'category']);
            $table->index(['affected_type', 'affected_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_audit_issues');
    }
};
