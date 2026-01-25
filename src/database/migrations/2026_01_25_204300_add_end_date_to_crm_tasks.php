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
        Schema::table('crm_tasks', function (Blueprint $table) {
            $table->timestamp('end_date')->nullable()->after('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table) {
            $table->dropColumn('end_date');
        });
    }
};
