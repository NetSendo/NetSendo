<?php

namespace App\Http\Middleware;

use App\Events\SubscriberUnsubscribed;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * A sleeping NetSendo Cloud instance (config netsendo.dormant, env NETSENDO_DORMANT=true).
 *
 * The subscription has ended; the data is kept and the owner can resume the instance. While
 * it sleeps:
 * - unsubscribe links in messages sent before keep working: the terms of sale promise it,
 *   the list owner cannot sign in to record the request, and mailbox providers expect a
 *   working one-click unsubscribe;
 * - nothing is sent: no confirmation email, no automations, no webhooks;
 * - every other page, the API and sign-in answer 503 with the "instance asleep" page.
 *
 * The hosting provider also stops the queue worker and the scheduler; the scheduled tasks
 * and the queue worker check the same switch (routes/console.php, AppServiceProvider).
 */
class DormantMode
{
    public const LOCALES = ['en', 'pl', 'de', 'es'];

    private const HEADERS = [
        'X-Robots-Tag' => 'noindex, nofollow',
        'Cache-Control' => 'no-store',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('netsendo.dormant')) {
            return $next($request);
        }

        $this->silence();

        $path = trim($request->path(), '/');

        if ($path === 'up') {
            return $next($request);
        }

        // /unsubscribe/{subscriber}, /{list}, /{list}/process, /process-all (routes/web.php)
        if (preg_match('#^unsubscribe/[^/]+(/[^/]+){0,2}$#', $path)) {
            $segments = explode('/', $path);

            // GET /unsubscribe/{subscriber}/{list} normally emails a confirmation link first.
            // Asleep, nothing is sent: the person confirms on the page, which POSTs the same
            // signed URL as an RFC 8058 one-click request.
            if ($request->isMethod('GET') && count($segments) === 3 && $segments[2] !== 'process-all') {
                return $this->unsubscribePage($request);
            }

            return $next($request);
        }

        return $this->asleep($request);
    }

    /** Nothing may leave a sleeping instance. */
    private function silence(): void
    {
        config([
            'mail.default' => 'array',
            'queue.connections.dormant' => ['driver' => 'null'],
            'queue.default' => 'dormant',
        ]);

        // Automations, webhooks and the "you have been unsubscribed" email; the unsubscribe
        // itself (status, dropping what was planned for the list) is written by the controller
        Event::forget(SubscriberUnsubscribed::class);
    }

    private function asleep(Request $request): Response
    {
        $headers = self::HEADERS + ['Retry-After' => '86400'];

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'error' => 'instance_asleep',
                'message' => 'This NetSendo instance is asleep: its subscription has ended.',
            ], 503, $headers);
        }

        return response()->view('dormant.asleep', ['locale' => $this->locale($request)], 503, $headers);
    }

    private function unsubscribePage(Request $request): Response
    {
        $locale = $this->locale($request);

        if (! URL::hasValidSignature($request)) {
            return response()->view('dormant.unsubscribe', ['locale' => $locale, 'action' => null], 403, self::HEADERS);
        }

        // The raw query string: the signature covers the parameters in their original order
        $action = $request->url() . '?' . $request->server->get('QUERY_STRING');

        return response()->view('dormant.unsubscribe', ['locale' => $locale, 'action' => $action], 200, self::HEADERS);
    }

    private function locale(Request $request): string
    {
        return $request->getPreferredLanguage(self::LOCALES) ?? 'en';
    }
}
