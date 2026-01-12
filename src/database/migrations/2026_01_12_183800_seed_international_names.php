<?php

use Database\Seeders\InternationalNamesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Seeds international names for gender detection in different countries.
     */
    public function up(): void
    {
        // Run the InternationalNamesSeeder to populate international names
        Artisan::call('db:seed', [
            '--class' => InternationalNamesSeeder::class,
            '--force' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove international names (all countries except PL which has its own seeder)
        \App\Models\Name::where('source', 'system')
            ->whereIn('country', ['DE', 'CZ', 'SK', 'FR', 'IT', 'ES', 'UK', 'US'])
            ->delete();
    }
};
