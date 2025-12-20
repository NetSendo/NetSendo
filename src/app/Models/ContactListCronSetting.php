<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactListCronSetting extends Model
{
    protected $fillable = [
        'contact_list_id',
        'use_defaults',
        'volume_per_minute',
        'schedule',
    ];

    protected $casts = [
        'use_defaults' => 'boolean',
        'volume_per_minute' => 'integer',
        'schedule' => 'array',
    ];

    /**
     * Relacja do listy kontaktów
     */
    public function contactList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class);
    }

    /**
     * Pobierz efektywny harmonogram (własny lub globalny)
     */
    public function getEffectiveSchedule(): array
    {
        if ($this->use_defaults || empty($this->schedule)) {
            return CronSetting::getGlobalSchedule();
        }

        return $this->schedule;
    }

    /**
     * Pobierz efektywny limit wiadomości/minutę
     */
    public function getEffectiveVolumePerMinute(): int
    {
        if ($this->use_defaults || $this->volume_per_minute === null) {
            return (int) CronSetting::getValue('volume_per_minute', 100);
        }

        return $this->volume_per_minute;
    }

    /**
     * Sprawdź czy wysyłka jest dozwolona w danym momencie
     */
    public function isDispatchAllowedNow(?\DateTimeInterface $dateTime = null): bool
    {
        $dateTime = $dateTime ?? now();
        
        $schedule = $this->getEffectiveSchedule();
        $dayName = strtolower($dateTime->format('l')); // monday, tuesday, etc.
        
        if (!isset($schedule[$dayName])) {
            return true; // Domyślnie dozwolone
        }

        $daySchedule = $schedule[$dayName];

        // Sprawdź czy dzień jest włączony
        if (!($daySchedule['enabled'] ?? true)) {
            return false;
        }

        // Oblicz minuty od północy
        $minuteOfDay = ((int) $dateTime->format('G') * 60) + (int) $dateTime->format('i');
        
        $start = $daySchedule['start'] ?? 0;
        $end = $daySchedule['end'] ?? 1440;

        // Jeśli start == end, dzień jest wyłączony
        if ($start === $end) {
            return false;
        }

        return $minuteOfDay >= $start && $minuteOfDay <= $end;
    }

    /**
     * Pobierz lub utwórz ustawienia dla listy
     */
    public static function getOrCreateForList(int $contactListId): static
    {
        return static::firstOrCreate(
            ['contact_list_id' => $contactListId],
            [
                'use_defaults' => true,
                'volume_per_minute' => null,
                'schedule' => null,
            ]
        );
    }
}
