<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;

class CampaignPlan extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    /**
     * Activity log name prefix
     */
    protected $activityLogName = 'campaign_plan';

    protected $fillable = [
        'user_id',
        'name',
        'status',
        // Business Context
        'industry',
        'business_model',
        'campaign_goal',
        'campaign_language',
        'average_order_value',
        'margin_percent',
        'decision_cycle_days',
        // Audience
        'audience_snapshot',
        'selected_lists',
        // AI Strategy
        'strategy',
        'forecast',
        // Export
        'exported_at',
        'exported_items',
    ];

    protected $casts = [
        'audience_snapshot' => 'array',
        'selected_lists' => 'array',
        'strategy' => 'array',
        'forecast' => 'array',
        'exported_items' => 'array',
        'exported_at' => 'datetime',
        'average_order_value' => 'decimal:2',
        'margin_percent' => 'decimal:2',
    ];

    /**
     * Available industries for dropdown
     */
    public static function getIndustries(): array
    {
        return [
            'ecommerce' => 'eCommerce / Retail',
            'saas' => 'SaaS / Software',
            'education' => 'Education / Courses',
            'finance' => 'Finance / Insurance',
            'health' => 'Health / Wellness',
            'travel' => 'Travel / Hospitality',
            'food' => 'Food / Restaurant',
            'real_estate' => 'Real Estate',
            'automotive' => 'Automotive',
            'beauty' => 'Beauty / Fashion',
            'b2b' => 'B2B Services',
            'nonprofit' => 'Nonprofit',
            'media' => 'Media / Entertainment',
            'other' => 'Other',
        ];
    }

    /**
     * Available business models
     */
    public static function getBusinessModels(): array
    {
        return [
            'ecommerce' => 'eCommerce',
            'lead_generation' => 'Lead Generation',
            'saas' => 'SaaS',
            'subscription' => 'Subscription',
            'marketplace' => 'Marketplace',
            'local_business' => 'Local Business',
        ];
    }

    /**
     * Available campaign goals
     */
    public static function getCampaignGoals(): array
    {
        return [
            'sales' => 'Sales / Revenue',
            'reactivation' => 'Reactivation',
            'onboarding' => 'Onboarding',
            'cross_sell' => 'Cross-sell / Upsell',
            'education' => 'Education',
            'lead_nurturing' => 'Lead Nurturing',
        ];
    }

    /**
     * Available campaign content languages
     */
    public static function getLanguages(): array
    {
        return [
            'en' => 'English',
            'pl' => 'Polski',
            'de' => 'Deutsch',
            'es' => 'Español',
            'fr' => 'Français',
            'it' => 'Italiano',
            'pt' => 'Português',
            'nl' => 'Nederlands',
            'sv' => 'Svenska',
            'cs' => 'Čeština',
            'uk' => 'Українська',
            'ru' => 'Русский',
        ];
    }

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function steps()
    {
        return $this->hasMany(CampaignPlanStep::class)->orderBy('order');
    }

    /**
     * Accessors
     */
    public function getTotalMessagesAttribute(): int
    {
        return $this->steps()->count();
    }

    public function getTimelineDaysAttribute(): int
    {
        $lastStep = $this->steps()->orderBy('order', 'desc')->first();
        return $lastStep ? $lastStep->delay_days : 0;
    }

    public function getEmailCountAttribute(): int
    {
        return $this->steps()->where('channel', 'email')->count();
    }

    public function getSmsCountAttribute(): int
    {
        return $this->steps()->where('channel', 'sms')->count();
    }

    /**
     * Scopes
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExported($query)
    {
        return $query->where('status', 'exported');
    }

    /**
     * Calculate projected revenue based on forecast
     */
    public function getProjectedRevenueAttribute(): ?float
    {
        if (!$this->forecast || !$this->average_order_value) {
            return null;
        }

        $audienceSize = $this->audience_snapshot['total_subscribers'] ?? 0;
        $conversionRate = $this->forecast['conversion_rate'] ?? 0;

        return $audienceSize * ($conversionRate / 100) * $this->average_order_value;
    }

    /**
     * Calculate ROI based on forecast
     */
    public function getProjectedRoiAttribute(): ?float
    {
        $revenue = $this->projected_revenue;
        if (!$revenue || !$this->margin_percent) {
            return null;
        }

        $profit = $revenue * ($this->margin_percent / 100);
        // Assume minimal cost for email/SMS campaigns
        $estimatedCost = $this->total_messages * 0.01; // $0.01 per message as baseline

        if ($estimatedCost <= 0) {
            return null;
        }

        return (($profit - $estimatedCost) / $estimatedCost) * 100;
    }
}
