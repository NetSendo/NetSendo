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
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('position');
            $table->timestamp('score_updated_at')->nullable()->after('last_activity_at');
            $table->timestamp('last_decay_at')->nullable()->after('score_updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->dropColumn(['last_activity_at', 'score_updated_at', 'last_decay_at']);
        });
    }
};
