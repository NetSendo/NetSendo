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
        Schema::table('subscription_forms', function (Blueprint $table) {
            $table->string('success_title')->nullable()->after('success_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_forms', function (Blueprint $table) {
            $table->dropColumn('success_title');
        });
    }
};
