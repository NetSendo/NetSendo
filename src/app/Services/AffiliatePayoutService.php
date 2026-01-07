<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\AffiliatePayoutItem;
use App\Models\AffiliateProgram;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AffiliatePayoutService
{
    /**
     * Create a payout batch for a program within a date range.
     */
    public function createPayout(
        int $programId,
        string $periodStart,
        string $periodEnd,
        ?int $affiliateId = null
    ): AffiliatePayout {
        $program = AffiliateProgram::findOrFail($programId);

        // Get all payable commissions in the period
        $commissionsQuery = AffiliateCommission::whereHas('offer', function ($q) use ($programId) {
                $q->where('program_id', $programId);
            })
            ->where('status', 'payable')
            ->whereBetween('created_at', [$periodStart, $periodEnd . ' 23:59:59']);

        if ($affiliateId) {
            $commissionsQuery->where('affiliate_id', $affiliateId);
        }

        $commissions = $commissionsQuery->get();

        if ($commissions->isEmpty()) {
            throw new \Exception('No payable commissions found for this period.');
        }

        $totalAmount = $commissions->sum('commission_amount');

        // Create payout in a transaction
        $payout = DB::transaction(function () use ($program, $affiliateId, $periodStart, $periodEnd, $totalAmount, $commissions) {
            $payout = AffiliatePayout::create([
                'program_id' => $program->id,
                'affiliate_id' => $affiliateId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'total_amount' => $totalAmount,
                'currency' => $program->currency,
                'status' => 'pending',
            ]);

            // Create payout items and link commissions
            foreach ($commissions as $commission) {
                AffiliatePayoutItem::create([
                    'payout_id' => $payout->id,
                    'commission_id' => $commission->id,
                    'amount' => $commission->commission_amount,
                ]);

                // Update commission with payout reference
                $commission->update(['payout_id' => $payout->id]);
            }

            return $payout;
        });

        Log::info('Payout created', [
            'payout_id' => $payout->id,
            'program_id' => $programId,
            'total_amount' => $totalAmount,
            'commissions_count' => $commissions->count(),
        ]);

        return $payout;
    }

    /**
     * Mark a payout as completed.
     */
    public function completePayout(int $payoutId, ?string $paymentReference = null): AffiliatePayout
    {
        $payout = AffiliatePayout::findOrFail($payoutId);
        $payout->markAsCompleted($paymentReference);

        Log::info('Payout completed', [
            'payout_id' => $payoutId,
            'reference' => $paymentReference,
        ]);

        return $payout;
    }

    /**
     * Export payout to CSV.
     */
    public function exportToCsv(int $payoutId): StreamedResponse
    {
        $payout = AffiliatePayout::with([
            'items.commission.affiliate',
            'items.commission.conversion',
            'program',
        ])->findOrFail($payoutId);

        $filename = "payout_{$payout->id}_{$payout->period_start->format('Y-m-d')}_{$payout->period_end->format('Y-m-d')}.csv";

        return response()->streamDownload(function () use ($payout) {
            $handle = fopen('php://output', 'w');

            // Headers
            fputcsv($handle, [
                'Commission ID',
                'Affiliate ID',
                'Affiliate Name',
                'Affiliate Email',
                'Level',
                'Amount',
                'Currency',
                'Conversion Type',
                'Conversion Amount',
                'Conversion Date',
                'Payout Method',
                'Payout Details',
            ]);

            // Data rows
            foreach ($payout->items as $item) {
                $commission = $item->commission;
                $affiliate = $commission->affiliate;
                $conversion = $commission->conversion;

                fputcsv($handle, [
                    $commission->id,
                    $affiliate->id,
                    $affiliate->name,
                    $affiliate->email,
                    $commission->level,
                    number_format($item->amount, 2, '.', ''),
                    $commission->currency,
                    $conversion?->type ?? '-',
                    $conversion ? number_format($conversion->amount, 2, '.', '') : '-',
                    $conversion?->created_at?->format('Y-m-d H:i:s') ?? '-',
                    $affiliate->payout_method,
                    json_encode($affiliate->payout_details),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Export all payable commissions for manual processing.
     */
    public function exportPayableCommissions(int $programId): StreamedResponse
    {
        $commissions = AffiliateCommission::with(['affiliate', 'conversion', 'offer'])
            ->whereHas('offer', function ($q) use ($programId) {
                $q->where('program_id', $programId);
            })
            ->where('status', 'payable')
            ->get();

        $filename = "payable_commissions_{$programId}_" . now()->format('Y-m-d') . ".csv";

        return response()->streamDownload(function () use ($commissions) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Commission ID',
                'Affiliate ID',
                'Affiliate Name',
                'Affiliate Email',
                'Payout Method',
                'PayPal Email',
                'Bank Details',
                'Level',
                'Amount',
                'Currency',
                'Conversion ID',
                'Created At',
            ]);

            foreach ($commissions as $commission) {
                $affiliate = $commission->affiliate;
                $payoutDetails = $affiliate->payout_details ?? [];

                fputcsv($handle, [
                    $commission->id,
                    $affiliate->id,
                    $affiliate->name,
                    $affiliate->email,
                    $affiliate->payout_method,
                    $payoutDetails['paypal_email'] ?? '-',
                    $payoutDetails['bank_account'] ?? '-',
                    $commission->level,
                    number_format($commission->commission_amount, 2, '.', ''),
                    $commission->currency,
                    $commission->conversion_id,
                    $commission->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Get payout summary for a program.
     */
    public function getProgramPayoutSummary(int $programId): array
    {
        $payouts = AffiliatePayout::where('program_id', $programId);

        $pendingCommissions = AffiliateCommission::whereHas('offer', function ($q) use ($programId) {
                $q->where('program_id', $programId);
            })
            ->where('status', 'payable')
            ->whereNull('payout_id');

        return [
            'total_paid' => (float) (clone $payouts)->where('status', 'completed')->sum('total_amount'),
            'pending_payouts' => (clone $payouts)->where('status', 'pending')->count(),
            'pending_amount' => (float) (clone $payouts)->where('status', 'pending')->sum('total_amount'),
            'unpaid_commissions_count' => $pendingCommissions->count(),
            'unpaid_commissions_amount' => (float) $pendingCommissions->sum('commission_amount'),
        ];
    }

    /**
     * Get payouts grouped by affiliate.
     */
    public function getPayableByAffiliate(int $programId): \Illuminate\Support\Collection
    {
        return AffiliateCommission::with('affiliate')
            ->whereHas('offer', function ($q) use ($programId) {
                $q->where('program_id', $programId);
            })
            ->where('status', 'payable')
            ->whereNull('payout_id')
            ->get()
            ->groupBy('affiliate_id')
            ->map(function ($commissions, $affiliateId) {
                $affiliate = $commissions->first()->affiliate;
                return [
                    'affiliate_id' => $affiliateId,
                    'affiliate_name' => $affiliate->name,
                    'affiliate_email' => $affiliate->email,
                    'payout_method' => $affiliate->payout_method,
                    'commissions_count' => $commissions->count(),
                    'total_amount' => $commissions->sum('commission_amount'),
                    'currency' => $commissions->first()->currency,
                ];
            })
            ->values();
    }
}
