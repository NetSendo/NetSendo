<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LicenseVerificationService
{
    /**
     * Klucz do zaciemnienia URL (XOR obfuscation - nie jest to pełne szyfrowanie!)
     * Utrudnia bezpośrednie odczytanie URL z kodu źródłowego.
     */
    private const OBFUSCATION_KEY = 'NetSendo2024LicenseVerification';

    /**
     * Zaciemniony URL webhooka weryfikacyjnego.
     * Oryginalny URL: https://a.gregciupek.com/webhook/6fbffefc-1437-4858-9c41-d430571903ab
     */
    private string $obfuscatedWebhookUrl;

    public function __construct()
    {
        // URL jest zaciemniony podczas budowania - w runtime dekodowany
        $this->obfuscatedWebhookUrl = $this->getStoredObfuscatedUrl();
    }

    /**
     * Weryfikuj licencję przed aktywacją.
     * Wywołuje webhook i sprawdza czy licencja istnieje i jest aktywna.
     *
     * Format odpowiedzi sukces (tablica z danymi licencji):
     * [{"id": 3, "package": "SILVER", "license_key": "...", "is_active": true, ...}]
     *
     * Format odpowiedzi błąd (obiekt z valid: false):
     * {"valid": false, "error": "LICENSE_NOT_FOUND", "message": "..."}
     */
    public function verifyLicense(string $licenseKey, string $domain): array
    {
        $webhookUrl = $this->getVerificationWebhookUrl();

        if (!$webhookUrl) {
            Log::warning('License verification webhook URL not configured');
            return [
                'valid' => false,
                'message' => 'Serwer weryfikacji licencji jest niedostępny.',
            ];
        }

        try {
            $response = Http::timeout(30)->post($webhookUrl, [
                'action' => 'verify_license',
                'license_key' => $licenseKey,
                'domain' => $domain,
                'version' => config('netsendo.version'),
                'timestamp' => now()->toIso8601String(),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Format 1: Tablica z danymi licencji (sukces)
                // [{"id": 3, "package": "SILVER", "license_key": "...", "is_active": true, ...}]
                if (is_array($data) && isset($data[0]) && is_array($data[0])) {
                    $licenseData = $data[0];
                }
                // Format 1b: Pojedynczy obiekt licencji (sukces)
                // {"id": 3, "package": "SILVER", "license_key": "...", "is_active": true, ...}
                elseif (is_array($data) && isset($data['license_key']) && isset($data['is_active'])) {
                    $licenseData = $data;
                } else {
                    $licenseData = null;
                }

                // Jeśli mamy dane licencji
                if ($licenseData !== null) {
                    // Sprawdź czy licencja jest aktywna
                    if (isset($licenseData['is_active']) && $licenseData['is_active'] === true) {
                        return [
                            'valid' => true,
                            'plan' => strtoupper($licenseData['package'] ?? 'SILVER'),
                            'is_active' => true,
                            'expires_at' => $licenseData['expires_at'] ?? null,
                            'contact_email' => $licenseData['contact_email'] ?? null,
                            'message' => 'Licencja zweryfikowana pomyślnie.',
                        ];
                    }

                    // Licencja znaleziona ale nieaktywna
                    return [
                        'valid' => false,
                        'message' => 'Licencja została dezaktywowana.',
                    ];
                }

                // Format 2: Obiekt z valid: false (błąd)
                // {"valid": false, "error": "LICENSE_NOT_FOUND", "message": "..."}
                if (is_array($data) && isset($data['valid']) && $data['valid'] === false) {
                    return [
                        'valid' => false,
                        'error' => $data['error'] ?? 'UNKNOWN',
                        'message' => $data['message'] ?? 'Licencja nie została znaleziona.',
                    ];
                }

                // Format 3: Pusta odpowiedź lub null = licencja nie znaleziona
                if (empty($data) || $data === null) {
                    return [
                        'valid' => false,
                        'error' => 'LICENSE_NOT_FOUND',
                        'message' => 'Licencja nie została znaleziona w systemie.',
                    ];
                }

                // Format 4: n8n domyślna odpowiedź {"success": true} = licencja nie znaleziona
                // (workflow wykonany ale brak danych licencji w odpowiedzi)
                if (is_array($data) && isset($data['success']) && !isset($data['license_key']) && !isset($data['is_active'])) {
                    return [
                        'valid' => false,
                        'error' => 'LICENSE_NOT_FOUND',
                        'message' => 'Licencja nie została znaleziona w systemie.',
                    ];
                }

                // Nieznany format odpowiedzi
                Log::warning('Unknown license verification response format', ['data' => $data]);
                return [
                    'valid' => false,
                    'message' => 'Nieoczekiwany format odpowiedzi z serwera weryfikacji.',
                ];
            }

            Log::warning('License verification failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'valid' => false,
                'message' => 'Nie udało się zweryfikować licencji. Kod odpowiedzi: ' . $response->status(),
            ];

        } catch (\Exception $e) {
            Log::error('License verification error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'valid' => false,
                'message' => 'Błąd połączenia z serwerem weryfikacji licencji.',
            ];
        }
    }

    /**
     * Sprawdź aktualny status licencji (wywoływane przez cron).
     * Zwraca informacje o statusie z serwera zewnętrznego.
     *
     * Format odpowiedzi sukces (tablica z danymi licencji):
     * [{"id": 3, "package": "SILVER", "license_key": "...", "is_active": true, ...}]
     *
     * Format odpowiedzi błąd (obiekt z valid: false):
     * {"valid": false, "error": "LICENSE_NOT_FOUND", "message": "..."}
     */
    public function checkLicenseStatus(): array
    {
        $licenseKey = Setting::where('key', 'license_key')->first()?->value;
        $licensePlan = Setting::where('key', 'license_plan')->first()?->value;
        $domain = Setting::where('key', 'license_domain')->first()?->value ?? request()->getHost();

        if (!$licenseKey) {
            return [
                'has_license' => false,
                'message' => 'Brak aktywnej licencji.',
            ];
        }

        $webhookUrl = $this->getVerificationWebhookUrl();

        if (!$webhookUrl) {
            return [
                'has_license' => true,
                'checked' => false,
                'message' => 'Webhook weryfikacji niedostępny.',
            ];
        }

        try {
            $response = Http::timeout(30)->post($webhookUrl, [
                'action' => 'check_status',
                'license_key' => $licenseKey,
                'domain' => $domain,
                'version' => config('netsendo.version'),
                'current_plan' => $licensePlan,
                'timestamp' => now()->toIso8601String(),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Format 1: Tablica z danymi licencji (sukces)
                if (is_array($data) && isset($data[0]) && is_array($data[0])) {
                    $licenseData = $data[0];
                }
                // Format 1b: Pojedynczy obiekt licencji (sukces)
                elseif (is_array($data) && isset($data['license_key']) && isset($data['is_active'])) {
                    $licenseData = $data;
                } else {
                    $licenseData = null;
                }

                // Jeśli mamy dane licencji
                if ($licenseData !== null) {
                    $isActive = isset($licenseData['is_active']) && $licenseData['is_active'] === true;

                    return [
                        'has_license' => true,
                        'checked' => true,
                        'valid' => $isActive,
                        'is_active' => $isActive,
                        'plan' => strtoupper($licenseData['package'] ?? $licensePlan),
                        'expires_at' => $licenseData['expires_at'] ?? null,
                        'message' => $isActive ? 'Licencja aktywna.' : 'Licencja dezaktywowana.',
                        'should_deactivate' => !$isActive,
                    ];
                }

                // Format 2: Obiekt z valid: false (błąd - licencja nie znaleziona)
                if (is_array($data) && isset($data['valid']) && $data['valid'] === false) {
                    return [
                        'has_license' => true,
                        'checked' => true,
                        'valid' => false,
                        'is_active' => false,
                        'plan' => $licensePlan,
                        'expires_at' => null,
                        'message' => $data['message'] ?? 'Licencja nie została znaleziona.',
                        'should_deactivate' => true,
                    ];
                }

                // Nieznany format
                Log::warning('Unknown license status response format', ['data' => $data]);
                return [
                    'has_license' => true,
                    'checked' => false,
                    'message' => 'Nieoczekiwany format odpowiedzi.',
                ];
            }

            return [
                'has_license' => true,
                'checked' => false,
                'message' => 'Błąd odpowiedzi serwera: ' . $response->status(),
            ];

        } catch (\Exception $e) {
            Log::error('License status check error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'has_license' => true,
                'checked' => false,
                'message' => 'Błąd połączenia: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Dezaktywuj lokalną licencję (gdy serwer zwróci is_active: false).
     */
    public function deactivateLicense(): void
    {
        Setting::where('key', 'license_key')->delete();
        Setting::where('key', 'license_plan')->delete();
        Setting::where('key', 'license_expires_at')->delete();

        Log::info('License deactivated by verification system');
    }

    /**
     * Pobierz URL webhooka weryfikacyjnego (dekodowany).
     */
    private function getVerificationWebhookUrl(): ?string
    {
        try {
            return $this->deobfuscateUrl($this->obfuscatedWebhookUrl);
        } catch (\Exception $e) {
            Log::error('Failed to decode webhook URL', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Pobierz zaciemniony URL (przechowywany w kodzie).
     */
    private function getStoredObfuscatedUrl(): string
    {
        // Zaciemniony URL do weryfikacji licencji
        // Oryginalny: https://a.gregciupek.com/webhook/6fbffefc-1437-4858-9c41-d430571903ab
        return 'JhEAIxZUS0BTHlVGKQ4ADBsDAD1LEQYLRhQEFgEAASVKQjUHCAIKVFMfBXhaVEhaS1BuSEsKUlhOBUBaX1t5VE1jVg8G';
    }

    /**
     * Zaciemnij URL (XOR + base64).
     * Używane podczas konfiguracji - nie w runtime.
     */
    public function obfuscateUrl(string $url): string
    {
        $key = self::OBFUSCATION_KEY;
        $keyLen = strlen($key);
        $result = '';

        for ($i = 0; $i < strlen($url); $i++) {
            $result .= chr(ord($url[$i]) ^ ord($key[$i % $keyLen]));
        }

        return base64_encode($result);
    }

    /**
     * Dekoduj zaciemniony URL.
     */
    private function deobfuscateUrl(string $obfuscated): string
    {
        $key = self::OBFUSCATION_KEY;
        $keyLen = strlen($key);
        $decoded = base64_decode($obfuscated);
        $result = '';

        for ($i = 0; $i < strlen($decoded); $i++) {
            $result .= chr(ord($decoded[$i]) ^ ord($key[$i % $keyLen]));
        }

        return $result;
    }

    /**
     * Metoda pomocnicza do testowania zaciemnienia (tylko development).
     */
    public function testObfuscation(): array
    {
        $originalUrl = 'https://a.gregciupek.com/webhook/6fbffefc-1437-4858-9c41-d430571903ab';
        $obfuscated = $this->obfuscateUrl($originalUrl);
        $deobfuscated = $this->deobfuscateUrl($obfuscated);

        return [
            'original' => $originalUrl,
            'obfuscated' => $obfuscated,
            'deobfuscated' => $deobfuscated,
            'match' => $originalUrl === $deobfuscated,
        ];
    }
}
