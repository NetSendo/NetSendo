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
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // Technical name (only a-z, 0-9, _)
            $table->string('label', 255); // Display label
            $table->text('description')->nullable(); // Admin description
            $table->enum('type', ['text', 'number', 'date', 'select', 'checkbox', 'radio'])->default('text');
            $table->json('options')->nullable(); // Options for select/radio
            $table->string('default_value', 255)->nullable(); // Default value
            $table->boolean('is_public')->default(true); // Visible in public forms
            $table->boolean('is_required')->default(false); // Required field
            $table->boolean('is_static')->default(false); // Only editable by admin
            $table->enum('scope', ['global', 'list'])->default('global'); // global or per-list
            $table->foreignId('contact_list_id')->nullable()->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0); // Display order
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            // Unique constraint for field name per user and scope
            $table->unique(['user_id', 'name', 'contact_list_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
