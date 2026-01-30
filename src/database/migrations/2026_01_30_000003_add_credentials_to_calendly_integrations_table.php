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
        Schema::table('calendly_integrations', function (Blueprint $table) {
            // API credentials (per-user, encrypted in model)
            $table->text('client_id')->nullable()->after('user_id');
            $table->text('client_secret')->nullable()->after('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calendly_integrations', function (Blueprint $table) {
            $table->dropColumn(['client_id', 'client_secret']);
        });
    }
};
