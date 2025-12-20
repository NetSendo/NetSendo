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
        Schema::table('ai_integrations', function (Blueprint $table) {
            $table->unsignedInteger('max_tokens_small')->default(2000)->after('default_model');
            $table->unsignedInteger('max_tokens_large')->default(50000)->after('max_tokens_small');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_integrations', function (Blueprint $table) {
            $table->dropColumn(['max_tokens_small', 'max_tokens_large']);
        });
    }
};
