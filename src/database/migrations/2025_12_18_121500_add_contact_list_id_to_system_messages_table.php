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
        Schema::table('system_messages', function (Blueprint $table) {
            $table->foreignId('contact_list_id')->nullable()->after('slug')->constrained()->cascadeOnDelete();
            
            // Allow same slug for different lists
            $table->dropUnique('system_messages_slug_unique');
            $table->unique(['slug', 'contact_list_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_messages', function (Blueprint $table) {
            $table->dropForeign(['contact_list_id']);
            $table->dropUnique(['slug', 'contact_list_id']);
            $table->dropColumn('contact_list_id');
            
            // Restore original unique constraint (might fail if duplicates exist, but okay for down)
            $table->unique('slug');
        });
    }
};
