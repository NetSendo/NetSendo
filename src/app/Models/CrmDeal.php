<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Events\CrmDealStageChanged;

class CrmDeal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'crm_pipeline_id',
        'crm_stage_id',
        'crm_contact_id',
        'crm_company_id',
        'owner_id',
        'name',
        'value',
        'currency',
        'expected_close_date',
        'closed_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'expected_close_date' => 'date',
        'closed_at' => 'date',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function (CrmDeal $deal) {
            event(new \App\Events\CrmDealCreated($deal, auth()->id()));
        });
    }

    /**
     * Get the user (account owner) for this deal.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the pipeline for this deal.
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'crm_pipeline_id');
    }

    /**
     * Get the current stage of this deal.
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmStage::class, 'crm_stage_id');
    }

    /**
     * Get the contact associated with this deal.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    /**
     * Get the company associated with this deal.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'crm_company_id');
    }

    /**
     * Get the salesperson assigned to this deal.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get all activities for this deal.
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(CrmActivity::class, 'subject');
    }

    // ==================== SCOPES ====================

    /**
     * Scope a query to only include deals for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include open deals.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope a query to only include won deals.
     */
    public function scopeWon($query)
    {
        return $query->where('status', 'won');
    }

    /**
     * Scope a query to only include lost deals.
     */
    public function scopeLost($query)
    {
        return $query->where('status', 'lost');
    }

    // ==================== ACTIONS ====================

    /**
     * Move deal to a new stage.
     */
    public function moveToStage(CrmStage $newStage, ?int $movedById = null): void
    {
        $oldStage = $this->stage;

        // Update the stage
        $this->update(['crm_stage_id' => $newStage->id]);

        // Check if it's a closing stage
        if ($newStage->is_won) {
            $this->update([
                'status' => 'won',
                'closed_at' => now(),
            ]);
        } elseif ($newStage->is_lost) {
            $this->update([
                'status' => 'lost',
                'closed_at' => now(),
            ]);
        } elseif ($this->status !== 'open') {
            // Reopening a closed deal
            $this->update([
                'status' => 'open',
                'closed_at' => null,
            ]);
        }

        // Log the activity
        $this->activities()->create([
            'user_id' => $this->user_id,
            'created_by_id' => $movedById ?? auth()->id(),
            'type' => $newStage->is_won ? 'deal_won' : ($newStage->is_lost ? 'deal_lost' : 'stage_changed'),
            'content' => "Zmieniono etap z '{$oldStage->name}' na '{$newStage->name}'",
            'metadata' => [
                'old_stage_id' => $oldStage->id,
                'old_stage_name' => $oldStage->name,
                'new_stage_id' => $newStage->id,
                'new_stage_name' => $newStage->name,
            ],
        ]);

        // Also log on contact if exists
        if ($this->crm_contact_id) {
            $this->contact->activities()->create([
                'user_id' => $this->user_id,
                'created_by_id' => $movedById ?? auth()->id(),
                'type' => 'stage_changed',
                'content' => "Deal '{$this->name}' zmienił etap na '{$newStage->name}'",
                'metadata' => [
                    'deal_id' => $this->id,
                    'new_stage_name' => $newStage->name,
                ],
            ]);
        }

        // Fire event for automation hooks
        event(new CrmDealStageChanged($this, $oldStage, $newStage, $movedById));

        // Handle auto-task creation if configured
        $this->handleAutoTask($newStage);
    }

    /**
     * Handle auto-task creation when moving to a stage.
     */
    protected function handleAutoTask(CrmStage $stage): void
    {
        if (empty($stage->auto_task)) {
            return;
        }

        $taskConfig = $stage->auto_task;

        CrmTask::create([
            'user_id' => $this->user_id,
            'owner_id' => $this->owner_id,
            'crm_contact_id' => $this->crm_contact_id,
            'crm_deal_id' => $this->id,
            'title' => $taskConfig['title'] ?? "Follow-up: {$this->name}",
            'type' => $taskConfig['type'] ?? 'follow_up',
            'priority' => $taskConfig['priority'] ?? 'medium',
            'due_date' => now()->addDays($taskConfig['due_days'] ?? 2),
        ]);
    }

    /**
     * Get formatted value with currency.
     */
    public function getFormattedValueAttribute(): string
    {
        return number_format($this->value, 2, ',', ' ') . ' ' . $this->currency;
    }
}
