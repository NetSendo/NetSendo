<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyExchangeService
{
    /**
     * Supported currencies with their display names
     */
    public const CURRENCIES = [
        'EUR' => 'Euro',
        'USD' => 'Dolar amerykański',
        'GBP' => 'Funt brytyjski',
        'CHF' => 'Frank szwajcarski',
        'PLN' => 'Złoty polski',
        'CZK' => 'Korona czeska',
        'SEK' => 'Korona szwedzka',
        'NOK' => 'Korona norweska',
        'DKK' => 'Korona duńska',
        'CAD' => 'Dolar kanadyjski',
        'AUD' => 'Dolar australijski',
        'JPY' => 'Jen japoński',
        'CNY' => 'Juan chiński',
        'HUF' => 'Forint węgierski',
        'RON' => 'Lej rumuński',
        'BGN' => 'Lew bułgarski',
        'TRY' => 'Lira turecka',
        'UAH' => 'Hrywna ukraińska',
        'RUB' => 'Rubel rosyjski',
        'BRL' => 'Real brazylijski',
        'INR' => 'Rupia indyjska',
    ];

    /**
     * Cache key prefix for exchange rates
     */
    private const CACHE_PREFIX = 'currency_rates_';

    /**
     * Cache duration in seconds (24 hours)
     */
    private const CACHE_DURATION = 86400;

    /**
     * NBP API URL for exchange rates
     */
    private const NBP_API_URL = 'https://api.nbp.pl/api/exchangerates/tables/A?format=json';

    /**
     * Get exchange rate from one currency to another
     *
     * @param string $from Source currency code
     * @param string $to Target currency code
     * @return float|null Exchange rate or null if not available
     */
    public function getRate(string $from, string $to): ?float
    {
        if ($from === $to) {
            return 1.0;
        }

        $rates = $this->getRates();

        if (empty($rates)) {
            return null;
        }

        // All rates are relative to PLN as base currency
        $fromRate = $from === 'PLN' ? 1.0 : ($rates[$from] ?? null);
        $toRate = $to === 'PLN' ? 1.0 : ($rates[$to] ?? null);

        if ($fromRate === null || $toRate === null) {
            return null;
        }

        // Convert: first to PLN, then to target currency
        return $fromRate / $toRate;
    }

    /**
     * Convert amount from one currency to another
     *
     * @param float $amount Amount to convert
     * @param string $from Source currency code
     * @param string $to Target currency code
     * @return float|null Converted amount or null if conversion not possible
     */
    public function convert(float $amount, string $from, string $to): ?float
    {
        $rate = $this->getRate($from, $to);

        if ($rate === null) {
            return null;
        }

        return round($amount * $rate, 2);
    }

    /**
     * Get all exchange rates (relative to PLN)
     *
     * @return array<string, float> Currency code => rate to PLN
     */
    public function getRates(): array
    {
        return Cache::remember(self::CACHE_PREFIX . 'all', self::CACHE_DURATION, function () {
            return $this->fetchRatesFromApi();
        });
    }

    /**
     * Fetch rates from NBP API
     *
     * @return array<string, float>
     */
    private function fetchRatesFromApi(): array
    {
        try {
            $response = Http::timeout(10)->get(self::NBP_API_URL);

            if (!$response->successful()) {
                Log::warning('CurrencyExchangeService: NBP API returned non-success status', [
                    'status' => $response->status(),
                ]);
                return $this->getFallbackRates();
            }

            $data = $response->json();

            if (empty($data[0]['rates'])) {
                Log::warning('CurrencyExchangeService: NBP API returned empty rates');
                return $this->getFallbackRates();
            }

            $rates = [];
            foreach ($data[0]['rates'] as $rate) {
                $code = $rate['code'];
                if (isset(self::CURRENCIES[$code])) {
                    $rates[$code] = (float) $rate['mid'];
                }
            }

            // PLN is always 1.0 (base currency)
            $rates['PLN'] = 1.0;

            Log::info('CurrencyExchangeService: Fetched rates from NBP API', [
                'currencies_count' => count($rates),
                'date' => $data[0]['effectiveDate'] ?? 'unknown',
            ]);

            return $rates;

        } catch (\Exception $e) {
            Log::error('CurrencyExchangeService: Failed to fetch rates from NBP API', [
                'error' => $e->getMessage(),
            ]);
            return $this->getFallbackRates();
        }
    }

    /**
     * Get fallback rates in case API is unavailable
     * These are approximate rates and should only be used as a last resort
     *
     * @return array<string, float>
     */
    private function getFallbackRates(): array
    {
        return [
            'EUR' => 4.32,
            'USD' => 3.98,
            'GBP' => 5.05,
            'CHF' => 4.52,
            'PLN' => 1.0,
            'CZK' => 0.17,
            'SEK' => 0.38,
            'NOK' => 0.36,
            'DKK' => 0.58,
            'CAD' => 2.93,
            'AUD' => 2.58,
            'JPY' => 0.027,
            'CNY' => 0.55,
            'HUF' => 0.011,
            'RON' => 0.87,
            'BGN' => 2.21,
            'TRY' => 0.12,
            'UAH' => 0.096,
            'RUB' => 0.043,
            'BRL' => 0.67,
            'INR' => 0.047,
        ];
    }

    /**
     * Get list of supported currencies
     *
     * @return array<string, string> Currency code => display name
     */
    public function getSupportedCurrencies(): array
    {
        return self::CURRENCIES;
    }

    /**
     * Check if a currency is supported
     *
     * @param string $code Currency code
     * @return bool
     */
    public function isSupported(string $code): bool
    {
        return isset(self::CURRENCIES[$code]);
    }

    /**
     * Clear cached exchange rates
     *
     * @return void
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_PREFIX . 'all');
    }

    /**
     * Get formatted exchange rates for display
     *
     * @param string $baseCurrency Base currency for display
     * @return array<string, array{code: string, name: string, rate: float|null}>
     */
    public function getFormattedRates(string $baseCurrency = 'PLN'): array
    {
        $rates = $this->getRates();
        $result = [];

        foreach (self::CURRENCIES as $code => $name) {
            $rate = $this->getRate($baseCurrency, $code);
            $result[$code] = [
                'code' => $code,
                'name' => $name,
                'rate' => $rate,
            ];
        }

        return $result;
    }
}
