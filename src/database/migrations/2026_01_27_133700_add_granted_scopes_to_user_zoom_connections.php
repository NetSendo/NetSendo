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
        Schema::table('user_zoom_connections', function (Blueprint $table) {
            $table->text('granted_scopes')->nullable()->after('zoom_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_zoom_connections', function (Blueprint $table) {
            $table->dropColumn('granted_scopes');
        });
    }
};
