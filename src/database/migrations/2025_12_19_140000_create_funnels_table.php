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
        Schema::create('funnels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug', 64)->unique();
            $table->enum('status', ['active', 'paused', 'draft'])->default('draft');
            
            // Trigger configuration
            $table->enum('trigger_type', ['list_signup', 'tag_added', 'form_submit', 'manual'])->default('list_signup');
            $table->foreignId('trigger_list_id')->nullable()->constrained('contact_lists')->nullOnDelete();
            $table->unsignedBigInteger('trigger_form_id')->nullable();
            $table->string('trigger_tag')->nullable();
            
            // Stats cache
            $table->unsignedInteger('subscribers_count')->default(0);
            $table->unsignedInteger('completed_count')->default(0);
            
            // Additional settings (JSON)
            $table->json('settings')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index('trigger_list_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnels');
    }
};
