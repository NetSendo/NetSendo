<?php

namespace App\Http\Controllers;

use App\Services\PolishCompanyLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyLookupController extends Controller
{
    protected PolishCompanyLookupService $lookupService;

    public function __construct(PolishCompanyLookupService $lookupService)
    {
        $this->lookupService = $lookupService;
    }

    /**
     * Look up Polish company data by NIP or REGON.
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nip' => 'nullable|string|max:15',
            'regon' => 'nullable|string|max:20',
        ]);

        // Must provide either NIP or REGON
        if (empty($validated['nip']) && empty($validated['regon'])) {
            return response()->json([
                'success' => false,
                'message' => __('crm.companies.lookup.missing_identifier'),
            ], 422);
        }

        $result = null;

        // Prefer NIP lookup
        if (!empty($validated['nip'])) {
            $nip = preg_replace('/\D/', '', $validated['nip']);

            if (!$this->lookupService->validateNip($nip)) {
                return response()->json([
                    'success' => false,
                    'message' => __('crm.companies.lookup.invalid_nip'),
                ], 422);
            }

            $result = $this->lookupService->lookupByNip($nip);
        } elseif (!empty($validated['regon'])) {
            $regon = preg_replace('/\D/', '', $validated['regon']);

            if (!$this->lookupService->validateRegon($regon)) {
                return response()->json([
                    'success' => false,
                    'message' => __('crm.companies.lookup.invalid_regon'),
                ], 422);
            }

            $result = $this->lookupService->lookupByRegon($regon);
        }

        if ($result) {
            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => __('crm.companies.lookup.not_found'),
        ], 404);
    }
}
