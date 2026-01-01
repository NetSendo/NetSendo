<?php

namespace App\Http\Controllers;

use App\Models\Webinar;
use App\Services\Webinar\AutoWebinarService;
use App\Models\AutoWebinarSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class AutoWebinarController extends Controller
{
    public function __construct(protected AutoWebinarService $autoWebinarService) {}

    public function config(Webinar $webinar): Response
    {
        $this->authorize('update', $webinar);

        return Inertia::render('Webinars/AutoConfig', [
            'webinar' => $webinar->load(['schedule', 'chatScripts', 'products', 'ctas']),
            'scheduleTypes' => AutoWebinarSchedule::getTypes(),
            'daysOfWeek' => AutoWebinarSchedule::getDaysOfWeek(),
            'chatScriptsPreview' => $this->autoWebinarService->previewChatTimeline($webinar),
        ]);
    }

    public function saveSchedule(Request $request, Webinar $webinar): JsonResponse
    {
        $this->authorize('update', $webinar);

        $validated = $request->validate([
            'schedule_type' => 'required|in:fixed,recurring,on_demand,evergreen',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'integer|between:0,6',
            'times_of_day' => 'nullable|array',
            'times_of_day.*' => 'date_format:H:i',
            'fixed_dates' => 'nullable|array',
            'start_delay_minutes' => 'nullable|integer|min:1',
            'available_slots' => 'nullable|array',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'max_sessions_per_day' => 'nullable|integer|min:1',
            'max_attendees_per_session' => 'nullable|integer|min:1',
            'timezone' => 'string',
            'is_active' => 'boolean',
        ]);

        $schedule = $this->autoWebinarService->configureSchedule($webinar, $validated);

        return response()->json([
            'schedule' => $schedule,
            'next_sessions' => $this->autoWebinarService->getNextSessionTimes($webinar),
        ]);
    }

    public function importChat(Request $request, Webinar $webinar): JsonResponse
    {
        $this->authorize('update', $webinar);

        $validated = $request->validate([
            'source_session_id' => 'required|exists:webinar_sessions,id',
        ]);

        $session = \App\Models\WebinarSession::find($validated['source_session_id']);
        $imported = $this->autoWebinarService->importChatFromLive($webinar, $session);

        return response()->json([
            'imported_count' => $imported,
            'preview' => $this->autoWebinarService->previewChatTimeline($webinar),
        ]);
    }

    public function generateChat(Request $request, Webinar $webinar): JsonResponse
    {
        $this->authorize('update', $webinar);

        $validated = $request->validate([
            'duration_seconds' => 'required|integer|min:60',
            'density' => 'integer|min:1|max:10',
        ]);

        $generated = $this->autoWebinarService->generateRandomChatScripts(
            $webinar,
            $validated['duration_seconds'],
            $validated['density'] ?? 1
        );

        return response()->json([
            'generated_count' => $generated,
            'preview' => $this->autoWebinarService->previewChatTimeline($webinar),
        ]);
    }

    public function clearChat(Webinar $webinar): JsonResponse
    {
        $this->authorize('update', $webinar);

        $deleted = $this->autoWebinarService->clearChatScripts($webinar);

        return response()->json([
            'deleted_count' => $deleted,
        ]);
    }

    public function previewTimeline(Webinar $webinar): JsonResponse
    {
        $this->authorize('view', $webinar);

        return response()->json([
            'chat_scripts' => $this->autoWebinarService->previewChatTimeline($webinar),
            'products' => $webinar->products()->whereNotNull('pin_at_seconds')
                ->orderBy('pin_at_seconds')->get(),
            'ctas' => $webinar->ctas()->whereNotNull('show_at_seconds')
                ->orderBy('show_at_seconds')->get(),
        ]);
    }

    public function getNextSessions(Webinar $webinar): JsonResponse
    {
        $this->authorize('view', $webinar);

        return response()->json([
            'sessions' => $this->autoWebinarService->getNextSessionTimes($webinar, 10),
        ]);
    }

    public function convert(Webinar $webinar): JsonResponse
    {
        $this->authorize('update', $webinar);

        $success = $this->autoWebinarService->convertToAutoWebinar($webinar);

        if (!$success) {
            return response()->json(['error' => 'Could not convert webinar'], 400);
        }

        return response()->json([
            'webinar' => $webinar->fresh()->load('schedule'),
        ]);
    }
}
