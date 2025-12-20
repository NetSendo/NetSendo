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
        Schema::create('subscriber_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscriber_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();
            
            // Each subscriber can have only one value per field
            $table->unique(['subscriber_id', 'custom_field_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriber_field_values');
    }
};
