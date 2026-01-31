<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fixes the unique constraint on system_key to be per-user instead of global.
     * Each user can have their own copy of system automations with the same system_key.
     */
    public function up(): void
    {
        // Check if the old unique index exists and drop it
        $indexName = 'automation_rules_system_key_unique';
        $indexExists = $this->indexExists('automation_rules', $indexName);

        if ($indexExists) {
            Schema::table('automation_rules', function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
        }

        // Check if composite index already exists (in case migration was partially run)
        $compositeIndexName = 'automation_rules_user_system_key_unique';
        $compositeExists = $this->indexExists('automation_rules', $compositeIndexName);

        if (!$compositeExists) {
            Schema::table('automation_rules', function (Blueprint $table) use ($compositeIndexName) {
                // Add composite unique constraint on user_id + system_key
                // This allows each user to have their own copy of system automations
                $table->unique(['user_id', 'system_key'], $compositeIndexName);
            });
        }
    }

    /**
     * Check if an index exists on a table.
     */
    protected function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();

        $result = DB::select("
            SELECT COUNT(*) as count
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND INDEX_NAME = ?
        ", [$databaseName, $table, $indexName]);

        return $result[0]->count > 0;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $compositeIndexName = 'automation_rules_user_system_key_unique';

        if ($this->indexExists('automation_rules', $compositeIndexName)) {
            Schema::table('automation_rules', function (Blueprint $table) use ($compositeIndexName) {
                $table->dropUnique($compositeIndexName);
            });
        }

        $indexName = 'automation_rules_system_key_unique';

        if (!$this->indexExists('automation_rules', $indexName)) {
            Schema::table('automation_rules', function (Blueprint $table) {
                $table->unique('system_key');
            });
        }
    }
};
