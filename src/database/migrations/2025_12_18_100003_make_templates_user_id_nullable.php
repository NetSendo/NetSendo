<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Makes user_id nullable to support system starter templates.
     */
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['user_id']);
            
            // Make user_id nullable
            $table->foreignId('user_id')->nullable()->change();
            
            // Re-add foreign key with proper constraints
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
