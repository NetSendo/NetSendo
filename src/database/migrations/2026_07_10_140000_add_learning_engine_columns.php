<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brain 2.0 Phase 4 — learning engine.
 *
 * - ai_performance_snapshots.experiment_dimensions: structured hypothesis
 *   dimensions per campaign (send day/hour, subject features) so learning
 *   becomes data-driven instead of prose-in-prompts.
 * - ai_goals.target_metric/target_value/baseline_value: outcome-driven
 *   goals — completion is decided by a real metric reaching its target,
 *   not by the count of executed sub-plans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_performance_snapshots', function (Blueprint $table) {
            $table->json('experiment_dimensions')->nullable()->after('what_to_improve');
        });

        Schema::table('ai_goals', function (Blueprint $table) {
            $table->string('target_metric', 40)->nullable()->after('success_criteria'); // open_rate|click_rate|subscribers|revenue_30d
            $table->decimal('target_value', 12, 2)->nullable()->after('target_metric');
            $table->decimal('baseline_value', 12, 2)->nullable()->after('target_value');
        });
    }

    public function down(): void
    {
        Schema::table('ai_performance_snapshots', function (Blueprint $table) {
            $table->dropColumn('experiment_dimensions');
        });

        Schema::table('ai_goals', function (Blueprint $table) {
            $table->dropColumn(['target_metric', 'target_value', 'baseline_value']);
        });
    }
};
