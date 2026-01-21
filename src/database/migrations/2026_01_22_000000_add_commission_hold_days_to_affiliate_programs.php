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
        Schema::table('affiliate_programs', function (Blueprint $table) {
            $table->integer('commission_hold_days')->default(30)->after('max_levels');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affiliate_programs', function (Blueprint $table) {
            $table->dropColumn('commission_hold_days');
        });
    }
};
