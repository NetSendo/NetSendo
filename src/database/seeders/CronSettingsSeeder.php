<?php

namespace Database\Seeders;

use App\Models\CronSetting;
use Illuminate\Database\Seeder;

class CronSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ustawienia globalne
        $settings = [
            ['key' => 'volume_per_minute', 'value' => '100', 'description' => 'Maksymalna liczba wiadomości na minutę'],
            ['key' => 'daily_maintenance_hour', 'value' => '4', 'description' => 'Godzina operacji dziennych (0-23)'],
            
            // Harmonogram tygodniowy - domyślnie całodobowo
            ['key' => 'schedule.monday.enabled', 'value' => 'true', 'description' => null],
            ['key' => 'schedule.monday.start', 'value' => '0', 'description' => null],
            ['key' => 'schedule.monday.end', 'value' => '1440', 'description' => null],
            
            ['key' => 'schedule.tuesday.enabled', 'value' => 'true', 'description' => null],
            ['key' => 'schedule.tuesday.start', 'value' => '0', 'description' => null],
            ['key' => 'schedule.tuesday.end', 'value' => '1440', 'description' => null],
            
            ['key' => 'schedule.wednesday.enabled', 'value' => 'true', 'description' => null],
            ['key' => 'schedule.wednesday.start', 'value' => '0', 'description' => null],
            ['key' => 'schedule.wednesday.end', 'value' => '1440', 'description' => null],
            
            ['key' => 'schedule.thursday.enabled', 'value' => 'true', 'description' => null],
            ['key' => 'schedule.thursday.start', 'value' => '0', 'description' => null],
            ['key' => 'schedule.thursday.end', 'value' => '1440', 'description' => null],
            
            ['key' => 'schedule.friday.enabled', 'value' => 'true', 'description' => null],
            ['key' => 'schedule.friday.start', 'value' => '0', 'description' => null],
            ['key' => 'schedule.friday.end', 'value' => '1440', 'description' => null],
            
            ['key' => 'schedule.saturday.enabled', 'value' => 'true', 'description' => null],
            ['key' => 'schedule.saturday.start', 'value' => '0', 'description' => null],
            ['key' => 'schedule.saturday.end', 'value' => '1440', 'description' => null],
            
            ['key' => 'schedule.sunday.enabled', 'value' => 'true', 'description' => null],
            ['key' => 'schedule.sunday.start', 'value' => '0', 'description' => null],
            ['key' => 'schedule.sunday.end', 'value' => '1440', 'description' => null],
        ];

        foreach ($settings as $setting) {
            CronSetting::firstOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'description' => $setting['description']]
            );
        }

        $this->command->info('✅ Domyślne ustawienia CRON zostały dodane.');
    }
}
