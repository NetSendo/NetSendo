<?php

use Database\Seeders\PolishNamesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Seeds Polish names with gender and vocative forms for personalization.
     */
    public function up(): void
    {
        // Run the PolishNamesSeeder to populate Polish names with vocative forms
        Artisan::call('db:seed', [
            '--class' => PolishNamesSeeder::class,
            '--force' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove Polish system names
        \App\Models\Name::where('source', 'system')
            ->where('country', 'PL')
            ->delete();
    }
};
