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
        Schema::table('crm_follow_up_sequences', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_follow_up_sequences', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('crm_follow_up_sequences', 'default_key')) {
                $table->string('default_key')->nullable()->after('is_default');
            }
        });

        // Add index using Laravel 11 compatible approach (raw SQL check)
        $indexExists = \DB::select("SHOW INDEX FROM crm_follow_up_sequences WHERE Key_name = 'crm_follow_up_sequences_user_id_default_key_index'");
        if (empty($indexExists)) {
            Schema::table('crm_follow_up_sequences', function (Blueprint $table) {
                $table->index(['user_id', 'default_key']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_follow_up_sequences', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'default_key']);
            $table->dropColumn(['is_default', 'default_key']);
        });
    }
};
