<?php

namespace App\Http\Controllers;

use App\Models\ContactList;
use App\Models\EmailClick;
use App\Models\EmailOpen;
use App\Models\AiIntegration;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Subscriber;
use App\Services\DashboardStatsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GlobalStatsController extends Controller
{
    /**
     * Display global statistics page
     */
    public function index(Request $request)
    {
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        $stats = $this->calculateMonthlyStats($year, $month);

        return Inertia::render('Settings/GlobalStats/Index', [
            'stats' => $stats,
            'year' => (int) $year,
            'month' => (int) $month,
            'availableYears' => $this->getAvailableYears(),
        ]);
    }

    /**
     * API endpoint for monthly stats
     */
    public function getMonthlyStats(Request $request, int $year, int $month)
    {
        $stats = $this->calculateMonthlyStats($year, $month);
        return response()->json($stats);
    }

    /**
     * Get dashboard stats (for Dashboard.vue)
     *
     * GET /api/dashboard/stats?period=7|30|90
     */
    public function getDashboardStats(Request $request, DashboardStatsService $stats)
    {
        return response()->json(
            $stats->forUser($request->user(), (int) $request->query('period', DashboardStatsService::DEFAULT_PERIOD))
        );
    }

    /**
     * Export monthly stats to CSV
     */
    public function export(Request $request, int $year, int $month): StreamedResponse
    {
        $stats = $this->calculateMonthlyStats($year, $month);
        $filename = "stats_{$year}_{$month}.csv";

        return response()->streamDownload(function () use ($stats, $year, $month) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Summary
            fputcsv($handle, ['Statystyki NetSendo - ' . $month . '/' . $year], ';');
            fputcsv($handle, [], ';');
            fputcsv($handle, ['Podsumowanie'], ';');
            fputcsv($handle, ['Nowi subskrybenci', $stats['summary']['new_subscribers']], ';');
            fputcsv($handle, ['Wypisani', $stats['summary']['unsubscribed']], ';');
            fputcsv($handle, ['Usunięci', $stats['summary']['deleted']], ';');
            fputcsv($handle, ['Wysłane emaile', $stats['summary']['emails_sent']], ';');
            fputcsv($handle, ['Otwarcia', $stats['summary']['opens']], ';');
            fputcsv($handle, ['Kliknięcia', $stats['summary']['clicks']], ';');
            fputcsv($handle, ['Open Rate', $stats['summary']['open_rate'] . '%'], ';');
            fputcsv($handle, ['Click Rate', $stats['summary']['click_rate'] . '%'], ';');
            fputcsv($handle, [], ';');

            // Per list stats
            fputcsv($handle, ['Statystyki per lista'], ';');
            fputcsv($handle, ['Lista', 'Nowi', 'Aktywni', 'Wypisani', 'Emaile wysłane', 'Otwarcia', 'Kliknięcia'], ';');

            foreach ($stats['lists'] as $list) {
                fputcsv($handle, [
                    $list['name'],
                    $list['new_subscribers'],
                    $list['active_subscribers'],
                    $list['unsubscribed'],
                    $list['emails_sent'],
                    $list['opens'],
                    $list['clicks'],
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Calculate monthly statistics
     */
    private function calculateMonthlyStats(int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth();

        // Global summary
        $newSubscribers = Subscriber::whereBetween('created_at', [$startDate, $endDate])->count();
        $unsubscribed = Subscriber::whereBetween('updated_at', [$startDate, $endDate])
            ->where('status', 'unsubscribed')
            ->count();
        $deleted = Subscriber::onlyTrashed()
            ->whereBetween('deleted_at', [$startDate, $endDate])
            ->count();

        $emailsSent = DB::table('cron_job_logs')
            ->whereBetween('started_at', [$startDate, $endDate])
            ->sum('emails_sent') ?? 0;

        $opens = EmailOpen::whereBetween('created_at', [$startDate, $endDate])->count();
        $clicks = EmailClick::whereBetween('created_at', [$startDate, $endDate])->count();

        $openRate = $emailsSent > 0 ? round(($opens / $emailsSent) * 100, 1) : 0;
        $clickRate = $emailsSent > 0 ? round(($clicks / $emailsSent) * 100, 1) : 0;

        // Per list stats - don't eager load subscribers to avoid memory exhaustion
        $lists = ContactList::all();

        $listStats = [];
        foreach ($lists as $list) {
            $listNewSubs = Subscriber::whereHas('contactLists', fn($q) => $q->where('contact_lists.id', $list->id))
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            $listActiveSubs = Subscriber::whereHas('contactLists', fn($q) => $q->where('contact_lists.id', $list->id)
                    ->where('contact_list_subscriber.status', 'active'))
                ->count();

            $listUnsubscribed = Subscriber::whereHas('contactLists', fn($q) => $q->where('contact_lists.id', $list->id)
                    ->where('contact_list_subscriber.status', 'unsubscribed')
                    ->whereBetween('contact_list_subscriber.updated_at', [$startDate, $endDate]))
                ->count();

            // Get messages for this list (via pivot table)
            $messageIds = DB::table('contact_list_message')
                ->where('contact_list_id', $list->id)
                ->pluck('message_id');

            $listOpens = EmailOpen::whereIn('message_id', $messageIds)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            $listClicks = EmailClick::whereIn('message_id', $messageIds)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            // Estimate emails sent for this list (based on sent messages count * active subscribers)
            $sentMessagesCount = Message::whereIn('id', $messageIds)
                ->where('status', 'sent')
                ->whereBetween('updated_at', [$startDate, $endDate])
                ->count();
            $listEmailsSent = $sentMessagesCount * $listActiveSubs;

            $listStats[] = [
                'id' => $list->id,
                'name' => $list->name,
                'new_subscribers' => $listNewSubs,
                'active_subscribers' => $listActiveSubs,
                'unsubscribed' => $listUnsubscribed,
                'emails_sent' => $listEmailsSent,
                'opens' => $listOpens,
                'clicks' => $listClicks,
            ];
        }

        // Daily trend data for chart
        $dailyData = [];
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dayStart = $currentDate->copy()->startOfDay();
            $dayEnd = $currentDate->copy()->endOfDay();

            $dailyData[] = [
                'date' => $currentDate->format('Y-m-d'),
                'label' => $currentDate->format('d'),
                'new_subscribers' => Subscriber::whereBetween('created_at', [$dayStart, $dayEnd])->count(),
                'opens' => EmailOpen::whereBetween('created_at', [$dayStart, $dayEnd])->count(),
                'clicks' => EmailClick::whereBetween('created_at', [$dayStart, $dayEnd])->count(),
            ];

            $currentDate->addDay();
        }

        return [
            'summary' => [
                'new_subscribers' => $newSubscribers,
                'unsubscribed' => $unsubscribed,
                'deleted' => $deleted,
                'emails_sent' => $emailsSent,
                'opens' => $opens,
                'clicks' => $clicks,
                'open_rate' => $openRate,
                'click_rate' => $clickRate,
            ],
            'lists' => $listStats,
            'daily' => $dailyData,
            'period' => [
                'year' => $year,
                'month' => $month,
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Get available years for the dropdown
     */
    private function getAvailableYears(): array
    {
        $oldestSubscriber = Subscriber::withTrashed()->orderBy('created_at')->first();
        $startYear = $oldestSubscriber ? $oldestSubscriber->created_at->year : now()->year;

        $years = [];
        for ($y = now()->year; $y >= $startYear; $y--) {
            $years[] = $y;
        }

        return $years;
    }
}
