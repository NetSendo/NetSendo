<?php

namespace App\Services;

use App\Models\SubscriberDevice;

class DeviceFingerprintService
{
    /**
     * Common browser patterns
     */
    protected const BROWSERS = [
        'Edge' => '/Edg(?:e|A|iOS)?\/(\d+)/i',
        'Opera' => '/(?:OPR|Opera)[\/\s](\d+)/i',
        'Chrome' => '/Chrome\/(\d+)/i',
        'Safari' => '/Safari\/(\d+)/i',
        'Firefox' => '/Firefox\/(\d+)/i',
        'IE' => '/(?:MSIE|Trident.*rv:)\s*(\d+)/i',
        'Samsung Internet' => '/SamsungBrowser\/(\d+)/i',
    ];

    /**
     * Common OS patterns
     */
    protected const OPERATING_SYSTEMS = [
        'Windows 11' => '/Windows NT 10.*Win64/i',
        'Windows 10' => '/Windows NT 10/i',
        'Windows 8.1' => '/Windows NT 6\.3/i',
        'Windows 8' => '/Windows NT 6\.2/i',
        'Windows 7' => '/Windows NT 6\.1/i',
        'Windows Vista' => '/Windows NT 6\.0/i',
        'Windows XP' => '/Windows NT 5\.[12]/i',
        'macOS' => '/Mac OS X (\d+[\._]\d+)/i',
        'iOS' => '/(?:iPhone|iPad|iPod).*OS (\d+_\d+)/i',
        'Android' => '/Android (\d+(?:\.\d+)?)/i',
        'Linux' => '/Linux/i',
        'Chrome OS' => '/CrOS/i',
    ];

    /**
     * Parse User-Agent string and extract device information
     */
    public function parseUserAgent(string $userAgent): array
    {
        return [
            'device_type' => $this->detectDeviceType($userAgent),
            'browser' => $this->detectBrowser($userAgent),
            'browser_version' => $this->detectBrowserVersion($userAgent),
            'os' => $this->detectOS($userAgent),
            'os_version' => $this->detectOSVersion($userAgent),
        ];
    }

    /**
     * Detect device type from User-Agent
     */
    public function detectDeviceType(string $userAgent): string
    {
        // Tablets
        if (preg_match('/iPad|Android(?!.*Mobile)|Tablet|PlayBook|Silk/i', $userAgent)) {
            return SubscriberDevice::TYPE_TABLET;
        }

        // Mobile phones
        if (preg_match('/Mobile|iPhone|iPod|Android.*Mobile|webOS|BlackBerry|IEMobile|Opera Mini|Opera Mobi/i', $userAgent)) {
            return SubscriberDevice::TYPE_MOBILE;
        }

        return SubscriberDevice::TYPE_DESKTOP;
    }

    /**
     * Detect browser name from User-Agent
     */
    public function detectBrowser(string $userAgent): ?string
    {
        // Check for Edge first (it includes Chrome in UA)
        if (preg_match('/Edg(?:e|A|iOS)?/i', $userAgent)) {
            return 'Edge';
        }

        // Check for Opera (it includes Chrome in UA)
        if (preg_match('/OPR|Opera/i', $userAgent)) {
            return 'Opera';
        }

        // Samsung Internet (includes Chrome in UA)
        if (preg_match('/SamsungBrowser/i', $userAgent)) {
            return 'Samsung Internet';
        }

        // Regular browsers
        foreach (self::BROWSERS as $browser => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $browser;
            }
        }

        return null;
    }

    /**
     * Detect browser version from User-Agent
     */
    public function detectBrowserVersion(string $userAgent): ?string
    {
        $browser = $this->detectBrowser($userAgent);

        if (!$browser || !isset(self::BROWSERS[$browser])) {
            return null;
        }

        if (preg_match(self::BROWSERS[$browser], $userAgent, $matches)) {
            return $matches[1] ?? null;
        }

        return null;
    }

    /**
     * Detect OS name from User-Agent
     */
    public function detectOS(string $userAgent): ?string
    {
        foreach (self::OPERATING_SYSTEMS as $os => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                // Return base OS name (without version suffix like "macOS" not "macOS 12.0")
                return preg_replace('/\s+[\d\._]+$/', '', $os);
            }
        }

        return null;
    }

    /**
     * Detect OS version from User-Agent
     */
    public function detectOSVersion(string $userAgent): ?string
    {
        // macOS
        if (preg_match('/Mac OS X (\d+[\._]\d+(?:[\._]\d+)?)/i', $userAgent, $matches)) {
            return str_replace('_', '.', $matches[1]);
        }

        // iOS
        if (preg_match('/(?:iPhone|iPad|iPod).*OS (\d+_\d+(?:_\d+)?)/i', $userAgent, $matches)) {
            return str_replace('_', '.', $matches[1]);
        }

        // Android
        if (preg_match('/Android (\d+(?:\.\d+(?:\.\d+)?)?)/i', $userAgent, $matches)) {
            return $matches[1];
        }

        // Windows (version is in OS name itself)
        foreach (['11', '10', '8.1', '8', '7', 'Vista', 'XP'] as $version) {
            if (stripos($this->detectOS($userAgent) ?? '', $version) !== false) {
                return $version;
            }
        }

        return null;
    }

    /**
     * Generate a lightweight device fingerprint
     * This is NOT a full browser fingerprint - just basic characteristics
     */
    public function generateFingerprint(array $deviceInfo): string
    {
        $components = [
            $deviceInfo['device_type'] ?? 'unknown',
            $deviceInfo['browser'] ?? 'unknown',
            $deviceInfo['os'] ?? 'unknown',
            $deviceInfo['screen_resolution'] ?? 'unknown',
            $deviceInfo['language'] ?? 'unknown',
            $deviceInfo['timezone'] ?? 'unknown',
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Build complete device info array from request data
     */
    public function buildDeviceInfo(
        string $userAgent,
        ?string $screenResolution = null,
        ?string $language = null,
        ?string $timezone = null,
        ?string $ipAddress = null
    ): array {
        $parsed = $this->parseUserAgent($userAgent);

        $deviceInfo = [
            ...$parsed,
            'user_agent' => $userAgent,
            'screen_resolution' => $screenResolution,
            'language' => $language,
            'timezone' => $timezone,
            'ip_address' => $ipAddress,
        ];

        $deviceInfo['device_fingerprint'] = $this->generateFingerprint($deviceInfo);

        return $deviceInfo;
    }

    /**
     * Get a human-readable device summary
     */
    public function getDeviceSummary(string $userAgent): string
    {
        $info = $this->parseUserAgent($userAgent);
        $parts = [];

        if ($info['browser']) {
            $parts[] = $info['browser'] . ($info['browser_version'] ? ' ' . $info['browser_version'] : '');
        }

        if ($info['os']) {
            $parts[] = $info['os'];
        }

        if ($info['device_type'] !== SubscriberDevice::TYPE_DESKTOP) {
            $parts[] = ucfirst($info['device_type']);
        }

        return implode(' / ', $parts) ?: 'Unknown Device';
    }
}
