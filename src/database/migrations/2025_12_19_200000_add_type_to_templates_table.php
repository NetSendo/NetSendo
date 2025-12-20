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
        Schema::table('templates', function (Blueprint $table) {
            // Type: email (default templates), insert (reusable snippets), signature (email signatures)
            $table->string('type', 20)->default('email')->after('category');
            
            // Plain text version for inserts (HTML is in 'content')
            $table->text('content_plain')->nullable()->after('content');
            
            // Short description for inserts
            $table->string('description', 255)->nullable()->after('name');
            
            // Add index for faster type filtering
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'content_plain', 'description']);
        });
    }
};
