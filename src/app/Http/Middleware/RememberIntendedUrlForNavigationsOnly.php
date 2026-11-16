<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After signing in, a user goes back to the page they were turned away from ("intended URL").
 * Laravel remembers that URL for every signed-out GET that does not ask for JSON, and the
 * panel's background polls (Brain monitor, notifications, meeting reminders) are such requests:
 * a tab left open after the session expired kept overwriting it with a JSON endpoint, and the
 * next sign-in landed on that endpoint, shown by Inertia as an empty dialog over the login page.
 *
 * Only a navigation may set the intended URL: a document load or an Inertia visit. A fetch()
 * says "Sec-Fetch-Dest: empty"; what it would have remembered is put back as it was.
 */
class RememberIntendedUrlForNavigationsOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || $this->isNavigation($request)) {
            return $next($request);
        }

        $before = $request->session()->get('url.intended');

        $response = $next($request);

        if ($request->session()->get('url.intended') !== $before) {
            $before === null
                ? $request->session()->forget('url.intended')
                : $request->session()->put('url.intended', $before);
        }

        return $response;
    }

    /** Browsers without Sec-Fetch-* headers (and tests) are treated as navigations, as before. */
    private function isNavigation(Request $request): bool
    {
        if ($request->headers->has('X-Inertia')) {
            return true;
        }

        $destination = $request->headers->get('Sec-Fetch-Dest');

        return $destination === null || in_array($destination, ['document', 'iframe'], true);
    }
}
