<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Admin controller for database migrations and maintenance tasks.
 *
 * WARNING: This controller should only be accessible by super admins.
 */
class AdminMigrationController extends Controller
{
    /**
     * Check migration status.
     */
    public function status(Request $request): JsonResponse
    {
        // Only allow authenticated admin users
        if (!auth()->check() || auth()->id() !== 1) {
            abort(403, 'Unauthorized');
        }

        $pendingMigrations = [];
        $ranMigrations = [];

        try {
            // Get migration status
            Artisan::call('migrate:status');
            $output = Artisan::output();

            // Parse output to find pending migrations
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                if (str_contains($line, 'Pending')) {
                    // Extract migration name
                    if (preg_match('/^\s*(\d{4}_\d{2}_\d{2}_\d+_\w+)/', $line, $matches)) {
                        $pendingMigrations[] = $matches[1];
                    }
                } elseif (str_contains($line, 'Ran')) {
                    if (preg_match('/^\s*(\d{4}_\d{2}_\d{2}_\d+_\w+)/', $line, $matches)) {
                        $ranMigrations[] = $matches[1];
                    }
                }
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }

        // Check specific columns
        $columnStatus = [
            'crm_tasks.end_date' => Schema::hasColumn('crm_tasks', 'end_date'),
        ];

        return response()->json([
            'success' => true,
            'pending_count' => count($pendingMigrations),
            'pending_migrations' => $pendingMigrations,
            'ran_count' => count($ranMigrations),
            'column_status' => $columnStatus,
            'raw_output' => $output,
        ]);
    }

    /**
     * Run pending migrations.
     */
    public function migrate(Request $request): JsonResponse
    {
        // Only allow authenticated admin users (user ID 1)
        if (!auth()->check() || auth()->id() !== 1) {
            abort(403, 'Unauthorized');
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'message' => 'Migrations completed',
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
