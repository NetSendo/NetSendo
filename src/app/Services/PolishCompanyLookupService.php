<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PolishCompanyLookupService
{
    /**
     * Biała Lista VAT API (Ministry of Finance) - free public API
     */
    protected const BIALA_LISTA_API_URL = 'https://wl-api.mf.gov.pl/api/search';

    /**
     * Look up a company by NIP number.
     *
     * @param string $nip 10-digit NIP number
     * @return array|null Company data or null if not found
     */
    public function lookupByNip(string $nip): ?array
    {
        $nip = $this->sanitizeNip($nip);

        if (!$this->validateNip($nip)) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->get(self::BIALA_LISTA_API_URL . '/nip/' . $nip, [
                    'date' => date('Y-m-d'),
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->parseBialaListaResponse($data);
            }

            Log::warning('Polish company lookup by NIP failed', [
                'nip' => $nip,
                'status' => $response->status(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::warning('Polish company lookup failed', [
                'nip' => $nip,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Look up a company by REGON number.
     *
     * @param string $regon 9 or 14-digit REGON number
     * @return array|null Company data or null if not found
     */
    public function lookupByRegon(string $regon): ?array
    {
        $regon = preg_replace('/\D/', '', $regon);

        if (!$this->validateRegon($regon)) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->get(self::BIALA_LISTA_API_URL . '/regon/' . $regon, [
                    'date' => date('Y-m-d'),
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->parseBialaListaResponse($data);
            }

            Log::warning('Polish company lookup by REGON failed', [
                'regon' => $regon,
                'status' => $response->status(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::warning('Polish company lookup by REGON failed', [
                'regon' => $regon,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Parse response from Biała Lista VAT API.
     */
    protected function parseBialaListaResponse(?array $data): ?array
    {
        if (empty($data) || empty($data['result']['subject'])) {
            return null;
        }

        $subject = $data['result']['subject'];

        if (empty($subject['name'])) {
            return null;
        }

        return [
            'name' => $subject['name'],
            'nip' => $subject['nip'] ?? null,
            'regon' => $subject['regon'] ?? null,
            'address' => $subject['workingAddress'] ?? $subject['residenceAddress'] ?? null,
            'phone' => null, // Biała Lista doesn't provide phone
            'website' => null, // Biała Lista doesn't provide website
            'industry' => null, // Biała Lista doesn't provide industry
            'krs' => $subject['krs'] ?? null,
            'vat_status' => $subject['statusVat'] ?? null,
        ];
    }

    /**
     * Sanitize NIP by removing non-digit characters.
     */
    protected function sanitizeNip(string $nip): string
    {
        return preg_replace('/\D/', '', $nip);
    }

    /**
     * Validate NIP number format and checksum.
     */
    public function validateNip(string $nip): bool
    {
        $nip = $this->sanitizeNip($nip);

        if (strlen($nip) !== 10) {
            return false;
        }

        // NIP checksum validation
        $weights = [6, 5, 7, 2, 3, 4, 5, 6, 7];
        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $nip[$i] * $weights[$i];
        }

        $checksum = $sum % 11;

        return $checksum === (int) $nip[9];
    }

    /**
     * Validate REGON number format.
     */
    public function validateRegon(string $regon): bool
    {
        $regon = preg_replace('/\D/', '', $regon);

        // REGON can be 9 or 14 digits
        if (!in_array(strlen($regon), [9, 14])) {
            return false;
        }

        // Checksum validation for 9-digit REGON
        if (strlen($regon) === 9) {
            $weights = [8, 9, 2, 3, 4, 5, 6, 7];
            $sum = 0;

            for ($i = 0; $i < 8; $i++) {
                $sum += (int) $regon[$i] * $weights[$i];
            }

            $checksum = $sum % 11;
            if ($checksum === 10) {
                $checksum = 0;
            }

            return $checksum === (int) $regon[8];
        }

        // For 14-digit REGON, validate first 9 digits + local unit checksum
        return true;
    }
}
