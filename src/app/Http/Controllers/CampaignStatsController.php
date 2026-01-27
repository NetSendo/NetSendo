<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Services\CampaignStatsService;
use App\Services\CampaignAdvisorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignStatsController extends Controller
{
    public function __construct(
        protected CampaignStatsService $statsService,
        protected CampaignAdvisorService $advisorService
    ) {}

    /**
     * Display campaign statistics overview for all tags with messages.
     */
    public function index()
    {
        $tags = $this->statsService->getAllTagsWithStats(Auth::id());

        return Inertia::render('CampaignStats/Index', [
            'tags' => $tags,
        ]);
    }

    /**
     * Display detailed statistics for a specific campaign (tag).
     */
    public function show(Tag $tag)
    {
        $this->authorize('view', $tag);

        $stats = $this->statsService->getTagStats($tag);
        $messages = $this->statsService->getTagMessagesStats($tag);
        $trends = $this->statsService->calculateTrends($tag, Auth::id());

        return Inertia::render('CampaignStats/Show', [
            'tag' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ],
            'stats' => $stats,
            'messages' => $messages,
            'trends' => $trends,
        ]);
    }

    /**
     * API: Get statistics for a specific tag.
     */
    public function getTagStats(Tag $tag)
    {
        $this->authorize('view', $tag);

        return response()->json([
            'stats' => $this->statsService->getTagStats($tag),
            'trends' => $this->statsService->calculateTrends($tag, Auth::id()),
        ]);
    }

    /**
     * API: Generate AI analysis for a campaign.
     */
    public function generateAiAnalysis(Request $request, Tag $tag)
    {
        $this->authorize('view', $tag);

        $language = $request->input('language', app()->getLocale());

        try {
            $analysis = $this->advisorService->analyzeTagCampaign(
                $tag,
                Auth::user(),
                $language
            );

            return response()->json([
                'success' => true,
                'analysis' => $analysis,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export campaign statistics to CSV.
     */
    public function export(Tag $tag): StreamedResponse
    {
        $this->authorize('view', $tag);

        $messages = $this->statsService->getTagMessagesStats($tag);
        $stats = $this->statsService->getTagStats($tag);

        $filename = 'campaign_' . str_replace(' ', '_', $tag->name) . '_' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($messages, $stats, $tag) {
            $handle = fopen('php://output', 'w');

            // Campaign summary
            fputcsv($handle, ['Campaign Statistics: ' . $tag->name]);
            fputcsv($handle, []);
            fputcsv($handle, ['Summary']);
            fputcsv($handle, ['Total Messages', $stats['messages_count']]);
            fputcsv($handle, ['Total Sent', $stats['total_sent']]);
            fputcsv($handle, ['Total Opens', $stats['total_opens']]);
            fputcsv($handle, ['Total Clicks', $stats['total_clicks']]);
            fputcsv($handle, ['Open Rate', $stats['open_rate'] . '%']);
            fputcsv($handle, ['Click Rate', $stats['click_rate'] . '%']);
            fputcsv($handle, ['Bounce Rate', $stats['bounce_rate'] . '%']);
            fputcsv($handle, []);

            // Message details
            fputcsv($handle, ['Message Details']);
            fputcsv($handle, ['Subject', 'Status', 'Type', 'Send Date', 'Sent', 'Opens', 'Clicks', 'Open Rate', 'Click Rate', 'Failed']);

            foreach ($messages as $message) {
                fputcsv($handle, [
                    $message['subject'],
                    $message['status'],
                    $message['type'],
                    $message['send_at'] ?? 'N/A',
                    $message['sent'],
                    $message['opens'],
                    $message['clicks'],
                    $message['open_rate'] . '%',
                    $message['click_rate'] . '%',
                    $message['failed'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
