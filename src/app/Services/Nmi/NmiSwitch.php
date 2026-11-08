<?php

namespace App\Services\Nmi;

use App\Models\Setting;

/**
 * Whether NetSendo MTA (NMI) is switched on for the installation. An admin switches it on
 * and off in the panel (Mail Infrastructure); NMI_ENABLED=true in .env, as set up before
 * the switch existed, keeps it on. NMI_ENABLED=false or no NMI_ENABLED: the panel decides.
 */
class NmiSwitch
{
    public const SETTING = 'nmi_enabled';

    public static function enabled(): bool
    {
        return self::forcedByEnv()
            || filter_var(Setting::where('key', self::SETTING)->value('value'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function forcedByEnv(): bool
    {
        return filter_var(config('nmi.features.enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Switch on or off; the MTA gets or loses the logins at once
     */
    public static function set(bool $enabled): void
    {
        Setting::updateOrCreate(['key' => self::SETTING], ['value' => $enabled ? '1' : '0']);

        app(MtaAuthFile::class)->sync();
    }
}
