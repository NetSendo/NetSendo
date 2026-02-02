<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IpPool extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'description',
        'is_active',
        'max_ips',
        'warming_schedule',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'max_ips' => 'integer',
        'warming_schedule' => 'array',
    ];

    /**
     * Pool type constants
     */
    public const TYPE_SHARED = 'shared';
    public const TYPE_DEDICATED = 'dedicated';

    /**
     * Default warming schedule (emails per day)
     */
    public const DEFAULT_WARMING_SCHEDULE = [
        1 => 50,      // Day 1: 50 emails
        2 => 100,     // Day 2: 100 emails
        3 => 150,
        4 => 200,
        5 => 300,
        6 => 400,
        7 => 500,     // Week 1 end
        8 => 750,
        9 => 1000,
        10 => 1500,
        11 => 2000,
        12 => 2500,
        13 => 3000,
        14 => 4000,   // Week 2 end
        15 => 5000,
        16 => 6000,
        17 => 7500,
        18 => 10000,
        19 => 12500,
        20 => 15000,
        21 => 20000,  // Week 3 end
        22 => 25000,
        23 => 30000,
        24 => 40000,
        25 => 50000,
        26 => 60000,
        27 => 75000,
        28 => 100000, // Week 4 end - full capacity
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ipAddresses()
    {
        return $this->hasMany(DedicatedIpAddress::class);
    }

    /**
     * Get active IP addresses
     */
    public function activeIps()
    {
        return $this->ipAddresses()->where('is_active', true);
    }

    /**
     * Get warming schedule with defaults
     */
    public function getWarmingScheduleWithDefaults(): array
    {
        return $this->warming_schedule ?? self::DEFAULT_WARMING_SCHEDULE;
    }

    /**
     * Check if pool can accept more IPs
     */
    public function canAddMoreIps(): bool
    {
        return $this->ipAddresses()->count() < $this->max_ips;
    }

    /**
     * Check if this is a shared pool
     */
    public function isShared(): bool
    {
        return $this->type === self::TYPE_SHARED;
    }

    /**
     * Scopes
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeShared($query)
    {
        return $query->whereNull('user_id')->orWhere('type', self::TYPE_SHARED);
    }

    public function scopeDedicated($query)
    {
        return $query->where('type', self::TYPE_DEDICATED);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get available pools for a user (their dedicated + shared)
     */
    public static function getAvailableFor(int $userId)
    {
        return static::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhereNull('user_id');
        })->active()->get();
    }
}
