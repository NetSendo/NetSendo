<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Name extends Model
{
    protected $fillable = [
        'name',
        'gender',
        'country',
        'source',
        'user_id',
    ];

    /**
     * Available countries with their labels
     */
    public const COUNTRIES = [
        'PL' => 'Poland',
        'DE' => 'Germany',
        'CZ' => 'Czech Republic',
        'SK' => 'Slovakia',
        'FR' => 'France',
        'IT' => 'Italy',
        'ES' => 'Spain',
        'UK' => 'United Kingdom',
        'US' => 'United States',
    ];

    /**
     * Get the user that owns this custom name entry
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by country
     */
    public function scopeForCountry($query, string $country)
    {
        return $query->where('country', $country);
    }

    /**
     * Scope to filter by gender
     */
    public function scopeForGender($query, string $gender)
    {
        return $query->where('gender', $gender);
    }

    /**
     * Scope to get system names
     */
    public function scopeSystem($query)
    {
        return $query->where('source', 'system');
    }

    /**
     * Scope to get user-defined names
     */
    public function scopeUserDefined($query, ?int $userId = null)
    {
        $query->where('source', 'user');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query;
    }

    /**
     * Scope to get names available to a specific user (system + their own)
     */
    public function scopeAvailableTo($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('source', 'system')
              ->orWhere(function ($q2) use ($userId) {
                  $q2->where('source', 'user')
                     ->where('user_id', $userId);
              });
        });
    }

    /**
     * Search names by partial match
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where('name', 'like', $search . '%');
    }

    /**
     * Find gender for a given name
     */
    public static function findGender(string $firstName, ?string $country = 'PL', ?int $userId = null): ?string
    {
        $normalizedName = mb_strtolower(trim($firstName));

        // First check user-defined names if userId provided
        if ($userId) {
            $userName = self::where('name', $normalizedName)
                ->where('country', $country)
                ->where('source', 'user')
                ->where('user_id', $userId)
                ->first();

            if ($userName) {
                return $userName->gender;
            }
        }

        // Then check system names
        $systemName = self::where('name', $normalizedName)
            ->where('country', $country)
            ->where('source', 'system')
            ->first();

        return $systemName?->gender;
    }
}
