<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpProviderSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'api_key',
        'enabled',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'enabled' => 'boolean',
    ];

    protected $hidden = [
        'api_key',
    ];

    public const PROVIDERS = [
        'vultr' => [
            'name' => 'Vultr',
            'logo' => 'vultr',
        ],
        'linode' => [
            'name' => 'Linode',
            'logo' => 'linode',
        ],
        'digitalocean' => [
            'name' => 'DigitalOcean',
            'logo' => 'digitalocean',
        ],
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get provider settings for a user
     */
    public static function getForUser(int $userId): array
    {
        $settings = self::where('user_id', $userId)->get()->keyBy('provider');

        $result = [];
        foreach (self::PROVIDERS as $key => $provider) {
            $setting = $settings->get($key);
            $result[$key] = [
                'name' => $provider['name'],
                'configured' => $setting !== null && !empty($setting->api_key),
                'enabled' => $setting?->enabled ?? false,
            ];
        }

        return $result;
    }

    /**
     * Get decrypted API key for a provider
     */
    public static function getApiKey(int $userId, string $provider): ?string
    {
        $setting = self::where('user_id', $userId)
            ->where('provider', $provider)
            ->where('enabled', true)
            ->first();

        return $setting?->api_key;
    }
}
