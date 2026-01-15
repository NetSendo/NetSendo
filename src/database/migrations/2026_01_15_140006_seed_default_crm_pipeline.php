<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates default pipeline with stages for each user
     */
    public function up(): void
    {
        // Get all users (each user gets their own default pipeline)
        $users = User::all();

        foreach ($users as $user) {
            // Create default pipeline
            $pipelineId = DB::table('crm_pipelines')->insertGetId([
                'user_id' => $user->id,
                'name' => 'Sprzedaż B2B',
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create default stages
            $stages = [
                ['name' => 'Nowy', 'color' => '#6b7280', 'order' => 0, 'is_won' => false, 'is_lost' => false],
                ['name' => 'Kontakt', 'color' => '#3b82f6', 'order' => 1, 'is_won' => false, 'is_lost' => false],
                ['name' => 'Rozmowa', 'color' => '#8b5cf6', 'order' => 2, 'is_won' => false, 'is_lost' => false],
                ['name' => 'Oferta', 'color' => '#f59e0b', 'order' => 3, 'is_won' => false, 'is_lost' => false],
                ['name' => 'Negocjacje', 'color' => '#ef4444', 'order' => 4, 'is_won' => false, 'is_lost' => false],
                ['name' => 'Wygrany', 'color' => '#10b981', 'order' => 5, 'is_won' => true, 'is_lost' => false],
                ['name' => 'Przegrany', 'color' => '#6b7280', 'order' => 6, 'is_won' => false, 'is_lost' => true],
            ];

            foreach ($stages as $stage) {
                DB::table('crm_stages')->insert([
                    'crm_pipeline_id' => $pipelineId,
                    'name' => $stage['name'],
                    'color' => $stage['color'],
                    'order' => $stage['order'],
                    'is_won' => $stage['is_won'],
                    'is_lost' => $stage['is_lost'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove all pipelines and stages (cascades)
        DB::table('crm_pipelines')->delete();
    }
};
