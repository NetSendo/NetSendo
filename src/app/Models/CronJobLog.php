<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CronJobLog extends Model
{
    protected $fillable = [
        'job_name',
        'started_at',
        'completed_at',
        'status',
        'emails_sent',
        'emails_failed',
        'errors',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'emails_sent' => 'integer',
        'emails_failed' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Rozpocznij nowy log zadania
     */
    public static function startJob(string $jobName, array $metadata = []): static
    {
        return static::create([
            'job_name' => $jobName,
            'started_at' => now(),
            'status' => 'running',
            'emails_sent' => 0,
            'emails_failed' => 0,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Zakończ zadanie sukcesem
     */
    public function completeSuccess(int $emailsSent = 0, int $emailsFailed = 0): static
    {
        $this->update([
            'completed_at' => now(),
            'status' => 'success',
            'emails_sent' => $emailsSent,
            'emails_failed' => $emailsFailed,
        ]);

        return $this;
    }

    /**
     * Zakończ zadanie błędem
     */
    public function completeFailed(string $error, int $emailsSent = 0, int $emailsFailed = 0): static
    {
        $this->update([
            'completed_at' => now(),
            'status' => 'failed',
            'emails_sent' => $emailsSent,
            'emails_failed' => $emailsFailed,
            'errors' => $error,
        ]);

        return $this;
    }

    /**
     * Dodaj błąd do logu
     */
    public function appendError(string $error): static
    {
        $currentErrors = $this->errors ?? '';
        $this->update([
            'errors' => $currentErrors . ($currentErrors ? "\n" : '') . $error,
        ]);

        return $this;
    }

    /**
     * Inkrementuj licznik wysłanych
     */
    public function incrementSent(int $count = 1): static
    {
        $this->increment('emails_sent', $count);
        return $this;
    }

    /**
     * Inkrementuj licznik błędów
     */
    public function incrementFailed(int $count = 1): static
    {
        $this->increment('emails_failed', $count);
        return $this;
    }

    /**
     * Czas trwania w sekundach
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->completed_at) {
            return null;
        }

        return $this->completed_at->diffInSeconds($this->started_at);
    }

    /**
     * Pobierz ostatnie logi
     */
    public static function getRecent(int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return static::orderBy('started_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Wyczyść stare logi (starsze niż N dni)
     */
    public static function cleanupOld(int $days = 30): int
    {
        return static::where('started_at', '<', now()->subDays($days))->delete();
    }

    /**
     * Statystyki z ostatnich 24h
     */
    public static function getLast24HoursStats(): array
    {
        $since = now()->subHours(24);
        
        $logs = static::where('started_at', '>=', $since)->get();
        
        return [
            'total_runs' => $logs->count(),
            'successful' => $logs->where('status', 'success')->count(),
            'failed' => $logs->where('status', 'failed')->count(),
            'emails_sent' => $logs->sum('emails_sent'),
            'emails_failed' => $logs->sum('emails_failed'),
            'avg_duration' => $logs->whereNotNull('completed_at')->avg('duration'),
        ];
    }
}
