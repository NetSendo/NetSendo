<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Segment;
use App\Services\Segmentation\SegmentQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Saved segments, read-only: a message targets one with segment_id.
 */
class SegmentController extends Controller
{
    public function __construct(protected SegmentQueryService $segments)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $segments = Segment::forUser($request->user()->id)->orderBy('name')->get();

        return response()->json([
            'data' => $segments->map(fn (Segment $segment) => $this->present($segment)),
        ]);
    }

    /**
     * One segment with a fresh count.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $segment = Segment::forUser($request->user()->id)->find($id);

        if (!$segment) {
            return response()->json(['message' => 'Segment not found'], 404);
        }

        $this->segments->refreshCount($segment);

        return response()->json(['data' => $this->present($segment)]);
    }

    protected function present(Segment $segment): array
    {
        return [
            'id' => $segment->id,
            'name' => $segment->name,
            'description' => $segment->description,
            'match' => $segment->match,
            'conditions' => $segment->conditions ?? [],
            'subscribers_count' => $segment->subscribers_count,
            'counted_at' => $segment->counted_at?->toIso8601String(),
        ];
    }
}
