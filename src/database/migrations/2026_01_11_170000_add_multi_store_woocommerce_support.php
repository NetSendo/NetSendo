<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('woocommerce_settings', function (Blueprint $table) {
            // Add name column for friendly store identification
            $table->string('name', 255)->nullable()->after('user_id');

            // Add is_default flag
            $table->boolean('is_default')->default(false)->after('store_info');
        });

        // Drop the foreign key first (it depends on the unique index)
        Schema::table('woocommerce_settings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        // Drop the unique constraint on user_id to allow multiple stores
        Schema::table('woocommerce_settings', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });

        // Recreate foreign key without unique constraint
        Schema::table('woocommerce_settings', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Add unique constraint on user_id + store_url combination
        Schema::table('woocommerce_settings', function (Blueprint $table) {
            $table->unique(['user_id', 'store_url'], 'woocommerce_settings_user_store_unique');
        });

        // Set existing records as default with a generated name
        DB::table('woocommerce_settings')->whereNull('name')->update([
            'name' => DB::raw("CONCAT('Sklep #', id)"),
            'is_default' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the user_id + store_url unique constraint
        Schema::table('woocommerce_settings', function (Blueprint $table) {
            $table->dropUnique('woocommerce_settings_user_store_unique');
        });

        // Remove duplicate stores per user (keep only the default or first one)
        $users = DB::table('woocommerce_settings')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        foreach ($users as $userId) {
            $keepId = DB::table('woocommerce_settings')
                ->where('user_id', $userId)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->value('id');

            DB::table('woocommerce_settings')
                ->where('user_id', $userId)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        // Restore unique constraint on user_id
        Schema::table('woocommerce_settings', function (Blueprint $table) {
            $table->unique('user_id');
        });

        // Remove added columns
        Schema::table('woocommerce_settings', function (Blueprint $table) {
            $table->dropColumn(['name', 'is_default']);
        });
    }
};
