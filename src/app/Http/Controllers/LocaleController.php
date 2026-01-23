<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * Controller for handling locale/language switching.
 */
class LocaleController extends Controller
{
    /**
     * Update the user's locale preference.
     *
     * Stores the locale in session for all users and in the database
     * for authenticated users.
     */
    public function update(Request $request)
    {
        $supportedLocales = array_keys(Config::get('localization.supported_locales', []));

        $validated = $request->validate([
            'locale' => 'required|string|in:' . implode(',', $supportedLocales),
        ]);

        // Store in session (works for both guests and authenticated users)
        $request->session()->put('locale', $validated['locale']);

        // Store in cookie for persistence across sessions (1 year validity)
        $cookie = cookie('locale', $validated['locale'], 60 * 24 * 365); // 1 year in minutes

        // Update user preference in database if authenticated
        if ($request->user()) {
            $request->user()->update(['locale' => $validated['locale']]);
        }

        return back()->withCookie($cookie);
    }
}
