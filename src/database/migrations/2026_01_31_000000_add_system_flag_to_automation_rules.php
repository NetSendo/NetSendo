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
        Schema::table('automation_rules', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('is_active');
            $table->string('system_key', 100)->nullable()->unique()->after('is_system');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automation_rules', function (Blueprint $table) {
            $table->dropColumn(['is_system', 'system_key']);
        });
    }
};
