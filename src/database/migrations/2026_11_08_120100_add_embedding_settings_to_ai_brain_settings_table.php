<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which AI integration and model embed the knowledge base. Empty means the first integration
 * that can embed, with its default embedding model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_brain_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('embedding_integration_id')->nullable()->after('preferred_integration_id');
            $table->string('embedding_model', 191)->nullable()->after('embedding_integration_id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_brain_settings', function (Blueprint $table) {
            $table->dropColumn(['embedding_integration_id', 'embedding_model']);
        });
    }
};
