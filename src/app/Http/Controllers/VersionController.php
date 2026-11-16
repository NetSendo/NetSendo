<?php

namespace App\Http\Controllers;

use App\Services\UpdateCheckService;

class VersionController extends Controller
{
    public function __construct(
        private UpdateCheckService $updates
    ) {}

    /**
     * Check for available updates (sidebar). Uses the cached release list, so a page view
     * does not cost a GitHub API request; see UpdateCheckService.
     */
    public function check()
    {
        if (empty(config('netsendo.github_repo'))) {
            return response()->json([
                'current_version' => config('netsendo.version'),
                'updates_available' => false,
                'message' => 'Sprawdzanie aktualizacji jest wyłączone.',
            ]);
        }

        return response()->json($this->updates->status());
    }

    /**
     * Ask GitHub now (the refresh buttons), ignoring the cache.
     */
    public function refresh()
    {
        if (empty(config('netsendo.github_repo'))) {
            return $this->check();
        }

        return response()->json($this->updates->status(fresh: true));
    }

    /**
     * Get current version info.
     */
    public function current()
    {
        return response()->json([
            'version' => config('netsendo.version'),
            'app_name' => config('app.name'),
        ]);
    }

    /**
     * Full changelog for the Updates page, from the same cached release list.
     */
    public function changelog()
    {
        return response()->json([
            'releases' => $this->updates->releases() ?? [],
            'current_version' => config('netsendo.version'),
        ]);
    }
}
