<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignBenchmark extends Model
{
    use HasFactory;

    protected $fillable = [
        'industry',
        'campaign_type',
        'avg_open_rate',
        'avg_click_rate',
        'avg_conversion_rate',
        'avg_unsubscribe_rate',
        'recommended_messages',
        'recommended_timeline_days',
        'best_practices',
    ];

    protected $casts = [
        'best_practices' => 'array',
        'avg_open_rate' => 'decimal:2',
        'avg_click_rate' => 'decimal:2',
        'avg_conversion_rate' => 'decimal:2',
        'avg_unsubscribe_rate' => 'decimal:2',
    ];

    /**
     * Get benchmark for specific industry and campaign type
     */
    public static function getBenchmark(string $industry, ?string $campaignType = null): ?self
    {
        $query = self::where('industry', $industry);

        if ($campaignType) {
            $query->where('campaign_type', $campaignType);
        } else {
            $query->whereNull('campaign_type');
        }

        $benchmark = $query->first();

        // Fallback to industry-level if no campaign-specific benchmark
        if (!$benchmark && $campaignType) {
            $benchmark = self::where('industry', $industry)
                ->whereNull('campaign_type')
                ->first();
        }

        // Fallback to "other" industry
        if (!$benchmark) {
            $benchmark = self::where('industry', 'other')
                ->whereNull('campaign_type')
                ->first();
        }

        return $benchmark;
    }

    /**
     * Get all benchmarks for an industry
     */
    public static function getForIndustry(string $industry): array
    {
        return self::where('industry', $industry)
            ->orWhere('industry', 'other')
            ->get()
            ->keyBy('campaign_type')
            ->toArray();
    }

    /**
     * Scopes
     */
    public function scopeForIndustry($query, string $industry)
    {
        return $query->where('industry', $industry);
    }

    public function scopeForCampaignType($query, string $type)
    {
        return $query->where('campaign_type', $type);
    }
}
