<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UpdatesController extends Controller
{
    /**
     * Display the updates/changelog page.
     */
    public function index(): Response
    {
        return Inertia::render('Settings/Updates', [
            'currentVersion' => config('netsendo.version', '1.0.0'),
            'githubRepo' => config('netsendo.github_repo'),
            'githubReleasesUrl' => config('netsendo.github_releases_url'),
        ]);
    }
}
