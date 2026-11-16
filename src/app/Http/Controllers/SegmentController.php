<?php

namespace App\Http\Controllers;

use App\Models\CustomField;
use App\Models\Message;
use App\Models\MessageFieldFilter;
use App\Models\Segment;
use App\Models\Tag;
use App\Services\Segmentation\SegmentQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Saved dynamic segments (Contacts → Segments).
 */
class SegmentController extends Controller
{
    public function __construct(protected SegmentQueryService $segments)
    {
    }

    public function index(Request $request)
    {
        $segments = Segment::forUser($request->user()->id)
            ->withCount('messages')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'match', 'conditions', 'subscribers_count', 'counted_at', 'updated_at']);

        // Lists show a fresh number; one count per segment is cheap enough for
        // the handful of segments an account keeps, and stale ones are refreshed.
        foreach ($segments as $segment) {
            if (!$segment->counted_at || $segment->counted_at->lt(now()->subMinutes(10))) {
                $this->segments->refreshCount($segment);
            }
        }

        return Inertia::render('Segments/Index', [
            'segments' => $segments->map(fn (Segment $segment) => [
                'id' => $segment->id,
                'name' => $segment->name,
                'description' => $segment->description,
                'conditions_count' => count($segment->conditions ?? []),
                'subscribers_count' => $segment->subscribers_count,
                'messages_count' => $segment->messages_count,
                'updated_at' => $segment->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Segments/Builder', ['segment' => null] + $this->options($request));
    }

    public function edit(Request $request, Segment $segment)
    {
        $this->authorizeSegment($request, $segment);

        return Inertia::render('Segments/Builder', [
            'segment' => $segment->only(['id', 'name', 'description', 'match', 'conditions']),
        ] + $this->options($request));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $segment = Segment::create($data + ['user_id' => $request->user()->id]);
        $this->segments->refreshCount($segment);

        return redirect()->route('segments.index')->with('success', __('segments.saved'));
    }

    public function update(Request $request, Segment $segment)
    {
        $this->authorizeSegment($request, $segment);
        $segment->update($this->validated($request));
        $this->segments->refreshCount($segment);

        return redirect()->route('segments.index')->with('success', __('segments.saved'));
    }

    public function destroy(Request $request, Segment $segment)
    {
        $this->authorizeSegment($request, $segment);
        // Messages that used it go back to their lists as they are (nullOnDelete).
        $segment->delete();

        return redirect()->route('segments.index')->with('success', __('segments.deleted'));
    }

    /**
     * Live count and a few example addresses for unsaved conditions.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'match' => 'nullable|in:all,any',
            'conditions' => 'present|array',
        ]);

        $conditions = $this->segments->sanitize($request->input('conditions', []), $request->user());
        $query = $this->segments->apply(
            $this->segments->baseQuery($request->user()->id),
            $conditions,
            $request->input('match', Segment::MATCH_ALL)
        );

        return response()->json([
            'count' => (clone $query)->count(),
            'total' => $this->segments->baseQuery($request->user()->id)->count(),
            'sample' => $query->orderByDesc('subscribers.id')->limit(5)->pluck('subscribers.email'),
        ]);
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'match' => 'required|in:all,any',
            'conditions' => 'required|array|min:1',
        ]);

        $data['conditions'] = $this->segments->sanitize($data['conditions'], $request->user());

        return $data;
    }

    protected function options(Request $request): array
    {
        $userId = $request->user()->id;

        return [
            'lists' => $request->user()->accessibleLists()->select('id', 'name')->orderBy('name')->get(),
            'tags' => Tag::where('user_id', $userId)->orderBy('name')->get(['id', 'name']),
            'messages' => Message::where('user_id', $userId)
                ->where('channel', 'email')
                ->whereIn('status', ['sent', 'sending', 'scheduled'])
                ->orderByDesc('id')
                ->limit(200)
                ->get(['id', 'subject']),
            'fields' => CustomField::where('user_id', $userId)->orderBy('label')->get(['id', 'name', 'label', 'type', 'options'])
                ->map(fn (CustomField $field) => [
                    'id' => $field->id,
                    'label' => $field->label ?: $field->name,
                    'type' => $field->type,
                    'options' => $field->options,
                    'operators' => MessageFieldFilter::operatorsForType($field->type),
                ]),
        ];
    }

    protected function authorizeSegment(Request $request, Segment $segment): void
    {
        abort_unless((int) $segment->user_id === (int) $request->user()->id, 404);
    }
}
