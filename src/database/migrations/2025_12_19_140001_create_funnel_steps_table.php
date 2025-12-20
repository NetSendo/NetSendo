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
        Schema::create('funnel_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained()->onDelete('cascade');
            
            // Step type
            $table->enum('type', ['start', 'email', 'delay', 'condition', 'action', 'end'])->default('email');
            $table->string('name')->nullable(); // Optional step name for display
            
            // For email steps
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            
            // For delay steps
            $table->unsignedInteger('delay_value')->nullable();
            $table->enum('delay_unit', ['minutes', 'hours', 'days', 'weeks'])->nullable();
            
            // For condition steps
            $table->enum('condition_type', ['email_opened', 'email_clicked', 'link_clicked', 'tag_exists', 'field_value'])->nullable();
            $table->json('condition_config')->nullable();
            
            // For action steps
            $table->enum('action_type', ['add_tag', 'remove_tag', 'move_to_list', 'copy_to_list', 'webhook', 'unsubscribe', 'notify'])->nullable();
            $table->json('action_config')->nullable();
            
            // Flow positions (for visual builder)
            $table->integer('position_x')->default(250);
            $table->integer('position_y')->default(100);
            
            // Connections (for flow)
            $table->unsignedBigInteger('next_step_id')->nullable();
            $table->unsignedBigInteger('next_step_yes_id')->nullable(); // For conditions - true branch
            $table->unsignedBigInteger('next_step_no_id')->nullable();  // For conditions - false branch
            
            // Order for linear display/fallback
            $table->unsignedInteger('order')->default(0);
            
            $table->timestamps();
            
            // Indexes
            $table->index('funnel_id');
            $table->index('type');
        });
        
        // Add foreign keys for step connections after table creation
        Schema::table('funnel_steps', function (Blueprint $table) {
            $table->foreign('next_step_id')->references('id')->on('funnel_steps')->nullOnDelete();
            $table->foreign('next_step_yes_id')->references('id')->on('funnel_steps')->nullOnDelete();
            $table->foreign('next_step_no_id')->references('id')->on('funnel_steps')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funnel_steps', function (Blueprint $table) {
            $table->dropForeign(['next_step_id']);
            $table->dropForeign(['next_step_yes_id']);
            $table->dropForeign(['next_step_no_id']);
        });
        
        Schema::dropIfExists('funnel_steps');
    }
};
