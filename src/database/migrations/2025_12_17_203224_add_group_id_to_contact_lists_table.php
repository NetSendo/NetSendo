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
        Schema::table('contact_lists', function (Blueprint $table) {
            $table->foreignId('contact_list_group_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_lists', function (Blueprint $table) {
            $table->dropForeign(['contact_list_group_id']);
            $table->dropColumn('contact_list_group_id');
        });
    }
};
