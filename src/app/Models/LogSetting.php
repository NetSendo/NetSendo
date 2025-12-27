<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key with default fallback
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value by key
     */
    public static function setValue(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Get retention hours setting (default: 24)
     */
    public static function getRetentionHours(): int
    {
        return (int) static::getValue('retention_hours', 24);
    }
}
