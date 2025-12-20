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
        Schema::create('automation_rule_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscriber_id')->nullable()->constrained()->nullOnDelete();
            
            // Trigger info
            $table->string('trigger_event', 50);
            $table->json('trigger_data')->nullable(); // Context data that triggered the rule
            
            // Execution details
            $table->json('actions_executed'); // Details of each action and its result
            $table->enum('status', ['success', 'partial', 'failed', 'skipped']);
            $table->text('error_message')->nullable();
            
            // Timing
            $table->unsignedInteger('execution_time_ms')->nullable(); // Execution duration
            $table->timestamp('executed_at')->useCurrent();
            
            // Indexes
            $table->index('automation_rule_id');
            $table->index('subscriber_id');
            $table->index('status');
            $table->index('executed_at');
            $table->index(['automation_rule_id', 'executed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_rule_logs');
    }
};
