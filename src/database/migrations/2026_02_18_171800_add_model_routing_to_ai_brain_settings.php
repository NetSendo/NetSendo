<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_brain_settings', function (Blueprint $table) {
            $table->json('model_routing')->nullable()->after('preferred_model');
        });
    }

    public function down(): void
    {
        Schema::table('ai_brain_settings', function (Blueprint $table) {
            $table->dropColumn('model_routing');
        });
    }
};
