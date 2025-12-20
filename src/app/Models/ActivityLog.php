<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLog extends Model
{
    public $timestamps = false;
    
    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'properties',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Available actions for the activity log
     */
    public const ACTIONS = [
        // Authentication
        'auth.login' => 'Logowanie',
        'auth.logout' => 'Wylogowanie',
        'auth.failed' => 'Nieudane logowanie',
        
        // Subscribers
        'subscriber.created' => 'Dodano subskrybenta',
        'subscriber.updated' => 'Zaktualizowano subskrybenta',
        'subscriber.deleted' => 'Usunięto subskrybenta',
        'subscriber.imported' => 'Zaimportowano subskrybentów',
        'subscriber.unsubscribed' => 'Subskrybent wypisał się',
        
        // Messages
        'message.created' => 'Utworzono wiadomość',
        'message.updated' => 'Zaktualizowano wiadomość',
        'message.deleted' => 'Usunięto wiadomość',
        'message.sent' => 'Wysłano wiadomość',
        'message.scheduled' => 'Zaplanowano wiadomość',
        
        // Lists
        'list.created' => 'Utworzono listę',
        'list.updated' => 'Zaktualizowano listę',
        'list.deleted' => 'Usunięto listę',
        
        // Templates
        'template.created' => 'Utworzono szablon',
        'template.updated' => 'Zaktualizowano szablon',
        'template.deleted' => 'Usunięto szablon',
        
        // Automations
        'automation.created' => 'Utworzono automatyzację',
        'automation.updated' => 'Zaktualizowano automatyzację',
        'automation.deleted' => 'Usunięto automatyzację',
        'automation.triggered' => 'Uruchomiono automatyzację',
        
        // Funnels
        'funnel.created' => 'Utworzono lejek',
        'funnel.updated' => 'Zaktualizowano lejek',
        'funnel.deleted' => 'Usunięto lejek',
        
        // Settings
        'settings.updated' => 'Zaktualizowano ustawienia',
        'mailbox.created' => 'Dodano skrzynkę pocztową',
        'mailbox.updated' => 'Zaktualizowano skrzynkę',
        'mailbox.deleted' => 'Usunięto skrzynkę',
        'api_key.created' => 'Utworzono klucz API',
        'api_key.deleted' => 'Usunięto klucz API',
        
        // Forms
        'form.created' => 'Utworzono formularz',
        'form.updated' => 'Zaktualizowano formularz',
        'form.deleted' => 'Usunięto formularz',
        'form.submission' => 'Nowe zgłoszenie formularza',
        
        // Backup
        'backup.created' => 'Utworzono kopię zapasową',
        'backup.restored' => 'Przywrócono kopię zapasową',
        'backup.deleted' => 'Usunięto kopię zapasową',
    ];

    /**
     * Get the user that performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the associated model
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('model');
    }

    /**
     * Log an activity
     */
    public static function log(string $action, ?Model $model = null, array $properties = []): static
    {
        return static::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->getKey(),
            'properties' => !empty($properties) ? $properties : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Scope: filter by user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: filter by action
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope: filter by action pattern (e.g. 'subscriber.*')
     */
    public function scopeByActionPattern($query, string $pattern)
    {
        $pattern = str_replace('*', '%', $pattern);
        return $query->where('action', 'like', $pattern);
    }

    /**
     * Scope: filter by date range
     */
    public function scopeInDateRange($query, $from, $to = null)
    {
        $query->where('created_at', '>=', $from);
        
        if ($to) {
            $query->where('created_at', '<=', $to);
        }
        
        return $query;
    }

    /**
     * Scope: filter by model type
     */
    public function scopeForModel($query, string $modelClass, ?int $modelId = null)
    {
        $query->where('model_type', $modelClass);
        
        if ($modelId !== null) {
            $query->where('model_id', $modelId);
        }
        
        return $query;
    }

    /**
     * Get the human-readable action name
     */
    public function getActionNameAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    /**
     * Get available action categories for filtering
     */
    public static function getActionCategories(): array
    {
        $categories = [];
        
        foreach (self::ACTIONS as $action => $label) {
            $category = explode('.', $action)[0];
            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }
            $categories[$category][$action] = $label;
        }
        
        return $categories;
    }

    /**
     * Cleanup old logs
     */
    public static function cleanup(int $days = 90): int
    {
        return static::where('created_at', '<', now()->subDays($days))->delete();
    }

    /**
     * Get recent logs
     */
    public static function getRecent(int $limit = 50)
    {
        return static::with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get statistics for a date range
     */
    public static function getStats($from, $to = null): array
    {
        $query = static::query();
        
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }
        
        $logs = $query->get();
        
        // Group by action category
        $byCategory = [];
        foreach ($logs as $log) {
            $category = explode('.', $log->action)[0];
            $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;
        }
        
        return [
            'total' => $logs->count(),
            'by_category' => $byCategory,
            'unique_users' => $logs->pluck('user_id')->unique()->filter()->count(),
        ];
    }
}
