<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to detect and set the application locale.
 *
 * Detection priority:
 * 1. User preference (authenticated users, stored in DB)
 * 2. Session value (guests)
 * 3. Browser Accept-Language header (auto-detection)
 * 4. Default locale from config
 */
class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->detectLocale($request);
        App::setLocale($locale);

        return $next($request);
    }

    /**
     * Detect the preferred locale based on priority rules.
     */
    protected function detectLocale(Request $request): string
    {
        $supported = array_keys(Config::get('localization.supported_locales', []));
        $default = Config::get('localization.default_locale', 'en');

        // Priority 1: User preference (if authenticated)
        if ($request->user() && $request->user()->locale) {
            $userLocale = $request->user()->locale;
            if (in_array($userLocale, $supported)) {
                return $userLocale;
            }
        }

        // Priority 2: Session
        if ($request->session()->has('locale')) {
            $sessionLocale = $request->session()->get('locale');
            if (in_array($sessionLocale, $supported)) {
                return $sessionLocale;
            }
        }

        // Priority 3: Browser Accept-Language header
        $browserLocale = $this->parseAcceptLanguage($request, $supported);
        if ($browserLocale) {
            return $browserLocale;
        }

        // Priority 4: Default locale
        return $default;
    }

    /**
     * Parse the Accept-Language header and find a matching supported locale.
     */
    protected function parseAcceptLanguage(Request $request, array $supported): ?string
    {
        $acceptLanguage = $request->header('Accept-Language');

        if (!$acceptLanguage) {
            return null;
        }

        // Parse Accept-Language header (e.g., "en-US,en;q=0.9,de;q=0.8")
        $languages = [];
        foreach (explode(',', $acceptLanguage) as $lang) {
            $parts = explode(';', trim($lang));
            $langCode = strtolower(substr(trim($parts[0]), 0, 2)); // Get first 2 chars (e.g., "en" from "en-US")
            $quality = isset($parts[1]) ? (float) str_replace('q=', '', $parts[1]) : 1.0;
            $languages[$langCode] = $quality;
        }

        // Sort by quality (highest first)
        arsort($languages);

        // Find first matching supported locale
        foreach (array_keys($languages) as $lang) {
            if (in_array($lang, $supported)) {
                return $lang;
            }
        }

        return null;
    }
}
