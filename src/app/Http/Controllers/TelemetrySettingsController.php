<?php

namespace App\Http\Controllers;

use App\Services\Telemetry\TelemetryReport;
use App\Services\Telemetry\TelemetryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Settings → Telemetry (administrator only): the opt-in switch, the installation id and the
 * exact report the installation sends. See docs/TELEMETRY.md.
 */
class TelemetrySettingsController extends Controller
{
    public const DOCS_URL = 'https://netsendo.com/en/docs/telemetry';

    public function __construct(private TelemetryService $telemetry) {}

    public function index(Request $request, TelemetryReport $report)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        // Before the first opt-in there is no id yet: the preview shows a placeholder
        $id = $this->telemetry->instanceId();

        return Inertia::render('Settings/Telemetry/Index', [
            'telemetry' => [
                'enabled' => $this->telemetry->enabled(),
                'locked' => $this->telemetry->environmentOverride() !== null,
                'instance_id' => $id,
                'last_sent_at' => $this->telemetry->lastSentAt()?->toIso8601String(),
                'endpoint' => config('netsendo.telemetry.url'),
                'docs_url' => self::DOCS_URL,
                'preview' => $report->build($id ?? '00000000-0000-4000-8000-000000000000'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        if ($this->telemetry->environmentOverride() !== null) {
            return back()->with('error', __('telemetry.locked'));
        }
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);

        if ($validated['enabled']) {
            $this->telemetry->enable();
            $this->telemetry->dismissNotice();

            return back()->with('success', __('telemetry.saved_on'));
        }

        $this->telemetry->disable();
        $this->telemetry->dismissNotice();

        return back()->with('success', __('telemetry.saved_off'));
    }

    /** "No, thanks" on the one-time dashboard question */
    public function dismiss(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $this->telemetry->dismissNotice();

        return back();
    }
}
