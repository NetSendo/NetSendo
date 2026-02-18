<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_brain_settings', function (Blueprint $table) {
            $table->string('preferred_model', 100)->nullable()->after('preferred_language');
            $table->foreignId('preferred_integration_id')->nullable()->after('preferred_model')
                ->constrained('ai_integrations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_brain_settings', function (Blueprint $table) {
            $table->dropForeign(['preferred_integration_id']);
            $table->dropColumn(['preferred_model', 'preferred_integration_id']);
        });
    }
};
