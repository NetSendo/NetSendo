<?php

namespace App\Http\Controllers;

use App\Models\AutoWebinarChatScript;
use App\Models\Webinar;
use App\Models\WebinarChatMessage;
use App\Services\Webinar\AutoWebinarScriptBuilderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class AutoWebinarScriptController extends Controller
{
    public function __construct(protected AutoWebinarScriptBuilderService $scriptBuilder) {}

    /**
     * List all scripts for a webinar.
     */
    public function index(Request $request, Webinar $webinar): JsonResponse
    {
        $this->authorize('view', $webinar);

        $scripts = $webinar->chatScripts()
            ->when($request->get('active_only'), fn($q) => $q->active())
            ->orderBy('show_at_seconds')
            ->get();

        return response()->json([
            'scripts' => $scripts->map(fn($s) => $this->formatScript($s)),
            'total_duration' => $webinar->duration_minutes ? $webinar->duration_minutes * 60 : 3600,
        ]);
    }

    /**
     * Show scenario builder page.
     */
    public function builder(Webinar $webinar): Response
    {
        $this->authorize('update', $webinar);

        $scripts = $webinar->chatScripts()
            ->orderBy('show_at_seconds')
            ->get()
            ->map(fn($s) => $this->formatScript($s));

        return Inertia::render('Webinars/ScenarioBuilder', [
            'webinar' => $webinar->only(['id', 'name', 'type', 'duration_minutes']),
            'scripts' => $scripts,
            'messageTypes' => AutoWebinarChatScript::getTypes(),
        ]);
    }

    /**
     * Store a new script entry.
     */
    public function store(Request $request, Webinar $webinar): JsonResponse
    {
        $this->authorize('update', $webinar);

        $validated = $request->validate([
            'sender_name' => 'required|string|max:100',
            'message_text' => 'required|string|max:500',
            'message_type' => 'required|string|in:question,comment,reaction,testimonial,excitement',
            'show_at_seconds' => 'required|integer|min:0',
            'reaction_count' => 'nullable|integer|min:0|max:100',
            'delay_variance_seconds' => 'nullable|integer|min:0|max:30',
            'show_randomly' => 'nullable|boolean',
        ]);

        $script = AutoWebinarChatScript::create([
            'webinar_id' => $webinar->id,
            'sender_name' => $validated['sender_name'],
            'sender_avatar_seed' => uniqid(),
            'message_text' => $validated['message_text'],
            'message_type' => $validated['message_type'],
            'show_at_seconds' => $validated['show_at_seconds'],
            'reaction_count' => $validated['reaction_count'] ?? 0,
            'delay_variance_seconds' => $validated['delay_variance_seconds'] ?? 0,
            'show_randomly' => $validated['show_randomly'] ?? false,
            'is_active' => true,
            'sort_order' => $webinar->chatScripts()->count(),
        ]);

        return response()->json([
            'success' => true,
            'script' => $this->formatScript($script),
        ], 201);
    }

    /**
     * Update a script entry.
     */
    public function update(Request $request, Webinar $webinar, AutoWebinarChatScript $script): JsonResponse
    {
        $this->authorize('update', $webinar);

        if ($script->webinar_id !== $webinar->id) {
            abort(403);
        }

        $validated = $request->validate([
            'sender_name' => 'sometimes|string|max:100',
            'message_text' => 'sometimes|string|max:500',
            'message_type' => 'sometimes|string|in:question,comment,reaction,testimonial,excitement',
            'show_at_seconds' => 'sometimes|integer|min:0',
            'reaction_count' => 'nullable|integer|min:0|max:100',
            'delay_variance_seconds' => 'nullable|integer|min:0|max:30',
            'show_randomly' => 'nullable|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $script->update($validated);

        return response()->json([
            'success' => true,
            'script' => $this->formatScript($script->fresh()),
        ]);
    }

    /**
     * Delete a script entry.
     */
    public function destroy(Webinar $webinar, AutoWebinarChatScript $script): JsonResponse
    {
        $this->authorize('update', $webinar);

        if ($script->webinar_id !== $webinar->id) {
            abort(403);
        }

        $script->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Reorder scripts.
     */
    public function reorder(Request $request, Webinar $webinar): JsonResponse
    {
        $this->authorize('update', $webinar);

        $validated = $request->validate([
            'scripts' => 'required|array',
            'scripts.*.id' => 'required|exists:auto_webinar_chat_scripts,id',
            'scripts.*.show_at_seconds' => 'required|integer|min:0',
            'scripts.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['scripts'] as $item) {
            AutoWebinarChatScript::where('id', $item['id'])
                ->where('webinar_id', $webinar->id)
                ->update([
                    'show_at_seconds' => $item['show_at_seconds'],
                    'sort_order' => $item['sort_order'],
                ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Duplicate a script entry.
     */
    public function duplicate(Webinar $webinar, AutoWebinarChatScript $script): JsonResponse
    {
        $this->authorize('update', $webinar);

        if ($script->webinar_id !== $webinar->id) {
            abort(403);
        }

        $newScript = $script->replicate([
            'source_message_id',
            'is_original',
        ]);
        $newScript->show_at_seconds = $script->show_at_seconds + 10;
        $newScript->sender_avatar_seed = uniqid();
        $newScript->save();

        return response()->json([
            'success' => true,
            'script' => $this->formatScript($newScript),
        ]);
    }

    /**
     * Import messages from a previous live webinar.
     */
    public function importFromWebinar(Request $request, Webinar $webinar): JsonResponse
    {
        $this->authorize('update', $webinar);

        $validated = $request->validate([
            'source_webinar_id' => 'required|exists:webinars,id',
            'include_questions' => 'boolean',
            'include_comments' => 'boolean',
            'include_testimonials' => 'boolean',
            'min_likes' => 'nullable|integer|min:0',
        ]);

        $sourceWebinar = Webinar::where('id', $validated['source_webinar_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $importedCount = $this->scriptBuilder->importFromWebinar(
            $webinar,
            $sourceWebinar,
            $validated
        );

        return response()->json([
            'success' => true,
            'imported_count' => $importedCount,
        ]);
    }

    /**
     * Generate random script entries.
     */
    public function generateRandom(Request $request, Webinar $webinar): JsonResponse
    {
        $this->authorize('update', $webinar);

        $validated = $request->validate([
            'density' => 'sometimes|integer|min:1|max:10', // messages per minute
            'duration_minutes' => 'sometimes|integer|min:5|max:180',
            'include_questions' => 'boolean',
            'include_testimonials' => 'boolean',
            'include_excitement' => 'boolean',
        ]);

        $duration = ($validated['duration_minutes'] ?? $webinar->duration_minutes ?? 60) * 60;

        $generatedCount = $this->scriptBuilder->generateRandomScript(
            $webinar,
            $duration,
            $validated['density'] ?? 2,
            $validated
        );

        return response()->json([
            'success' => true,
            'generated_count' => $generatedCount,
        ]);
    }

    /**
     * Preview timeline.
     */
    public function preview(Webinar $webinar): JsonResponse
    {
        $this->authorize('view', $webinar);

        $scripts = $webinar->chatScripts()
            ->active()
            ->orderBy('show_at_seconds')
            ->get();

        // Group by time segments (every 5 minutes)
        $timeline = [];
        foreach ($scripts as $script) {
            $segment = floor($script->show_at_seconds / 300) * 5; // 5-minute segments
            $key = sprintf('%02d:%02d', floor($segment / 60), $segment % 60);

            if (!isset($timeline[$key])) {
                $timeline[$key] = [
                    'time' => $key,
                    'seconds' => $segment * 60,
                    'count' => 0,
                    'scripts' => [],
                ];
            }

            $timeline[$key]['count']++;
            $timeline[$key]['scripts'][] = $this->formatScript($script);
        }

        return response()->json([
            'timeline' => array_values($timeline),
            'total_scripts' => $scripts->count(),
            'average_density' => $webinar->duration_minutes
                ? round($scripts->count() / $webinar->duration_minutes, 1)
                : 0,
        ]);
    }

    /**
     * Clear all scripts.
     */
    public function clear(Webinar $webinar): JsonResponse
    {
        $this->authorize('update', $webinar);

        $webinar->chatScripts()->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Format script for API response.
     */
    protected function formatScript(AutoWebinarChatScript $script): array
    {
        return [
            'id' => $script->id,
            'sender_name' => $script->sender_name,
            'avatar_url' => $script->avatar_url,
            'message_text' => $script->message_text,
            'message_type' => $script->message_type,
            'show_at_seconds' => $script->show_at_seconds,
            'formatted_time' => sprintf(
                '%02d:%02d',
                floor($script->show_at_seconds / 60),
                $script->show_at_seconds % 60
            ),
            'reaction_count' => $script->reaction_count,
            'delay_variance_seconds' => $script->delay_variance_seconds,
            'show_randomly' => $script->show_randomly,
            'is_active' => $script->is_active,
            'is_original' => $script->is_original,
        ];
    }
}
