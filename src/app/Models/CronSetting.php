<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CronSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    /**
     * Pobierz wartość ustawienia po kluczu
     */
    public static function getValue(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Ustaw wartość ustawienia
     */
    public static function setValue(string $key, $value, ?string $description = null): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'description' => $description,
            ]
        );
    }

    /**
     * Pobierz wszystkie ustawienia jako array
     */
    public static function getAllAsArray(): array
    {
        return static::all()->pluck('value', 'key')->toArray();
    }

    /**
     * Pobierz domyślny harmonogram tygodniowy
     */
    public static function getDefaultSchedule(): array
    {
        return [
            'monday' => ['enabled' => true, 'start' => 0, 'end' => 1440],
            'tuesday' => ['enabled' => true, 'start' => 0, 'end' => 1440],
            'wednesday' => ['enabled' => true, 'start' => 0, 'end' => 1440],
            'thursday' => ['enabled' => true, 'start' => 0, 'end' => 1440],
            'friday' => ['enabled' => true, 'start' => 0, 'end' => 1440],
            'saturday' => ['enabled' => true, 'start' => 0, 'end' => 1440],
            'sunday' => ['enabled' => true, 'start' => 0, 'end' => 1440],
        ];
    }

    /**
     * Pobierz harmonogram globalny z bazy
     */
    public static function getGlobalSchedule(): array
    {
        $schedule = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        foreach ($days as $day) {
            $start = static::getValue("schedule.{$day}.start", 0);
            $end = static::getValue("schedule.{$day}.end", 1440);
            $enabled = static::getValue("schedule.{$day}.enabled", 'true');
            
            $schedule[$day] = [
                'enabled' => $enabled === 'true' || $enabled === true || $enabled === '1',
                'start' => (int) $start,
                'end' => (int) $end,
            ];
        }
        
        return $schedule;
    }

    /**
     * Zapisz harmonogram globalny do bazy
     */
    public static function setGlobalSchedule(array $schedule): void
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        foreach ($days as $day) {
            if (isset($schedule[$day])) {
                static::setValue("schedule.{$day}.enabled", $schedule[$day]['enabled'] ? 'true' : 'false');
                static::setValue("schedule.{$day}.start", $schedule[$day]['start']);
                static::setValue("schedule.{$day}.end", $schedule[$day]['end']);
            }
        }
    }
}
