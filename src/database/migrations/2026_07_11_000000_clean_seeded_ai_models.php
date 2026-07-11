<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove non-custom rows from `ai_models`.
     *
     * These were seeded from the default catalog when an integration was
     * created, and became stale as the catalog was refreshed — surfacing
     * outdated labels (e.g. "Claude 4.5 Opus (Najnowszy - Styczeń 2026)")
     * and superseded models in the pickers. The current catalog is now
     * injected at read time by AiIntegration::availableModels(), so these
     * cached rows are redundant. Custom models (is_custom = true) and any
     * list fetched on demand from a provider API are preserved / rebuildable.
     */
    public function up(): void
    {
        if (!Schema::hasTable('ai_models')) {
            return;
        }

        DB::table('ai_models')->where('is_custom', false)->delete();
    }

    /**
     * Irreversible: the removed rows were derived from the default catalog
     * and are regenerated automatically at read time, so there is nothing to
     * restore.
     */
    public function down(): void
    {
        // no-op
    }
};
