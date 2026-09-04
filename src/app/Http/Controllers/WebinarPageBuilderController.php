<?php

namespace App\Http\Controllers;

use App\Models\Webinar;
use App\Services\Webinar\WebinarPagePresets;
use App\Services\Webinar\WebinarPageRenderer;
use App\Services\Webinar\WebinarPageService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Visual builder for the public webinar funnel pages: registration page,
 * post-registration thank-you screen, post-purchase thank-you page and the
 * extra sections shown on the waiting room / replay pages.
 */
class WebinarPageBuilderController extends Controller
{
    public function edit(Webinar $webinar, string $page): Response
    {
        $this->authorize('update', $webinar);
        $page = $this->guardPage($page);

        $definition = WebinarPageService::definitionFor($webinar, $page);

        return Inertia::render('Webinars/PageBuilder', [
            'webinar' => [
                'id' => $webinar->id,
                'name' => $webinar->name,
                'slug' => $webinar->slug,
                'type' => $webinar->type,
                'status' => $webinar->status,
                'is_auto' => $webinar->isAutoWebinar(),
                'thumbnail_url' => $webinar->thumbnail_url,
                'scheduled_at' => $webinar->scheduled_at?->toIso8601String(),
                'public_url' => route('webinar.register', $webinar->slug),
                'thankyou_url' => route('webinar.thankyou', $webinar->slug),
            ],
            'page' => $page,
            'pages' => WebinarPageService::PAGES,
            'fullPages' => WebinarPageService::FULL_PAGES,
            'definition' => $definition,
            'catalog' => WebinarPageService::blocksForPage($page),
            'presets' => WebinarPagePresets::all(),
            'themeDefaults' => WebinarPageService::themeDefaults(),
            'options' => [
                'layouts' => WebinarPageService::LAYOUTS,
                'spacings' => WebinarPageService::SPACINGS,
                'alignments' => WebinarPageService::ALIGNMENTS,
                'radii' => WebinarPageService::RADII,
                'containers' => WebinarPageService::CONTAINERS,
                'fonts' => WebinarPageService::FONTS,
                'rowBackgrounds' => WebinarPageService::ROW_BACKGROUNDS,
            ],
            'builtPages' => collect(WebinarPageService::PAGES)
                ->mapWithKeys(fn ($key) => [$key => WebinarPageService::isBuilt($webinar, $key)])
                ->all(),
        ]);
    }

    /**
     * Save a page definition.
     */
    public function update(Request $request, Webinar $webinar, string $page)
    {
        $this->authorize('update', $webinar);
        $page = $this->guardPage($page);

        $validated = $this->validateDefinition($request);

        WebinarPageService::store($webinar, $page, $validated['definition']);

        return back()->with('success', __('webinars.builder.saved'));
    }

    /**
     * Drop the page definition — the public page falls back to the classic
     * template built from the webinar's content settings.
     */
    public function destroy(Webinar $webinar, string $page)
    {
        $this->authorize('update', $webinar);
        $page = $this->guardPage($page);

        WebinarPageService::forget($webinar, $page);

        return back()->with('success', __('webinars.builder.reset_done'));
    }

    /**
     * Build a starter definition from one of the presets, pre-filled with the
     * webinar's own content. Returned as JSON so the builder can drop it into
     * the canvas without a page reload (nothing is saved yet).
     */
    public function preset(Request $request, Webinar $webinar, string $page)
    {
        $this->authorize('update', $webinar);
        $page = $this->guardPage($page);

        $preset = $request->input('preset', 'midnight');

        if (!in_array($preset, WebinarPagePresets::PRESETS, true)) {
            throw ValidationException::withMessages(['preset' => 'Unknown preset.']);
        }

        return response()->json([
            'definition' => WebinarPagePresets::build($webinar, $preset, $page),
        ]);
    }

    /**
     * Render the draft currently open in the builder, so the preview pane can
     * show exactly what visitors will get.
     */
    public function preview(Request $request, Webinar $webinar, string $page)
    {
        $this->authorize('update', $webinar);
        $page = $this->guardPage($page);

        $validated = $this->validateDefinition($request);
        $definition = WebinarPageService::normalize($validated['definition'], $page);

        $html = view(
            in_array($page, WebinarPageService::FULL_PAGES, true)
                ? 'webinar.builder.page'
                : 'webinar.builder.preview-embed',
            [
                'webinar' => $webinar,
                'definition' => $definition,
                'ctx' => $this->previewContext($webinar, $page),
            ]
        )->render();

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Sample context so every block has something to render in the preview.
     */
    protected function previewContext(Webinar $webinar, string $page): array
    {
        $timezone = $webinar->timezone ?? config('app.timezone') ?? 'UTC';
        $start = $webinar->scheduled_at ?? now()->addDays(3)->setTime(19, 0);

        $sessions = [];
        if ($webinar->isAutoWebinar() && $webinar->schedule) {
            $sessions = $webinar->schedule->getNextSessionTimes(5);
        }
        if ($sessions === []) {
            $sessions = [
                $start->copy(),
                $start->copy()->addDay(),
                $start->copy()->addDays(2),
            ];
        }

        $registration = new \App\Models\WebinarRegistration([
            'email' => 'jan.kowalski@example.com',
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'access_token' => str_repeat('p', 64),
            'timezone' => $timezone,
        ]);
        $registration->setRelation('webinar', $webinar);
        $registration->setRelation('session', null);

        return [
            'title' => $webinar->name,
            'preview' => true,
            'registration' => $registration,
            'sessions' => $sessions,
            'canRegister' => true,
            'timezones' => ['Europe/Warsaw' => '(UTC+01:00) Warsaw', 'Europe/London' => '(UTC+00:00) London', 'UTC' => '(UTC+00:00) UTC'],
            'defaultTimezone' => $timezone,
            'scheduleTimezone' => $timezone,
            'displayTimezone' => $timezone,
            'start_at' => $start,
            'tokens' => WebinarPageRenderer::tokens($webinar, [
                'first_name' => 'Jan',
                'last_name' => 'Kowalski',
                'email' => 'jan.kowalski@example.com',
                'watch_url' => route('webinar.register', $webinar->slug),
            ]),
        ];
    }

    /**
     * Structural validation of the posted definition. The heavy lifting (types,
     * enums, unknown blocks) happens in WebinarPageService::normalize().
     */
    protected function validateDefinition(Request $request): array
    {
        return $request->validate([
            'definition' => 'required|array',
            'definition.enabled' => 'nullable|boolean',
            'definition.theme' => 'nullable|array',
            'definition.theme.*' => 'nullable',
            'definition.rows' => 'nullable|array|max:60',
            'definition.rows.*.id' => 'nullable|string|max:40',
            'definition.rows.*.layout' => 'nullable|string|max:10',
            'definition.rows.*.style' => 'nullable|array',
            'definition.rows.*.columns' => 'nullable|array|max:3',
            'definition.rows.*.columns.*.blocks' => 'nullable|array|max:40',
            'definition.rows.*.columns.*.blocks.*.type' => 'required|string|max:40',
            'definition.rows.*.columns.*.blocks.*.props' => 'nullable|array',
        ]);
    }

    protected function guardPage(string $page): string
    {
        abort_unless(in_array($page, WebinarPageService::PAGES, true), 404);

        return $page;
    }
}
