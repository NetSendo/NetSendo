<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignPlanStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_plan_id',
        'order',
        'channel',
        'message_type',
        'subject',
        'description',
        'delay_days',
        'delay_hours',
        'conditions',
        'content_hints',
    ];

    protected $casts = [
        'conditions' => 'array',
        'content_hints' => 'array',
    ];

    /**
     * Available message types
     */
    public static function getMessageTypes(): array
    {
        return [
            'educational' => 'Educational',
            'sales' => 'Sales / Promotional',
            'reminder' => 'Reminder',
            'social_proof' => 'Social Proof / Testimonial',
            'follow_up' => 'Follow-up',
            'onboarding' => 'Onboarding / Welcome',
            'reengagement' => 'Re-engagement',
            'announcement' => 'Announcement',
            'thank_you' => 'Thank You',
            'survey' => 'Survey / Feedback',
        ];
    }

    /**
     * Available condition triggers
     */
    public static function getConditionTriggers(): array
    {
        return [
            'opened' => 'Opened previous email',
            'not_opened' => 'Did NOT open previous email',
            'clicked' => 'Clicked in previous email',
            'not_clicked' => 'Did NOT click in previous email',
            'purchased' => 'Made a purchase',
            'not_purchased' => 'Did NOT purchase',
            'visited_page' => 'Visited specific page',
            'inactive_days' => 'Inactive for X days',
        ];
    }

    /**
     * Relationships
     */
    public function campaignPlan()
    {
        return $this->belongsTo(CampaignPlan::class);
    }

    /**
     * Get human-readable message type label
     */
    public function getMessageTypeLabelAttribute(): string
    {
        return self::getMessageTypes()[$this->message_type] ?? $this->message_type;
    }

    /**
     * Get human-readable channel label
     */
    public function getChannelLabelAttribute(): string
    {
        return $this->channel === 'email' ? 'Email' : 'SMS';
    }

    /**
     * Check if this step has conditions
     */
    public function getHasConditionsAttribute(): bool
    {
        return !empty($this->conditions);
    }

    /**
     * Get formatted delay string
     */
    public function getDelayStringAttribute(): string
    {
        if ($this->delay_days === 0 && $this->delay_hours === 0) {
            return 'Immediately';
        }

        $parts = [];
        if ($this->delay_days > 0) {
            $parts[] = $this->delay_days . ' ' . ($this->delay_days === 1 ? 'day' : 'days');
        }
        if ($this->delay_hours > 0) {
            $parts[] = $this->delay_hours . ' ' . ($this->delay_hours === 1 ? 'hour' : 'hours');
        }

        return 'After ' . implode(' and ', $parts);
    }
}
