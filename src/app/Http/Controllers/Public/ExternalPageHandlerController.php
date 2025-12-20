<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ExternalPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ExternalPageHandlerController extends Controller
{
    public function show(Request $request, ExternalPage $externalPage)
    {
        // Gather data from request (query params)
        $data = $request->all();

        // If redirect mode is enabled
        if ($externalPage->is_redirect) {
            $url = $externalPage->url;
            
            // Append data as query parameters if shared_fields is set
            // or just append everything if we want full pass-through?
            // The plan said "shared fields"
            
            $queryParams = [];
            
            // Only include fields that are in shared_fields list
            if ($externalPage->shared_fields) {
                foreach ($externalPage->shared_fields as $field) {
                    if (isset($data[$field])) {
                        $queryParams[$field] = $data[$field];
                    }
                }
            }
            
            // Also custom fields
            if ($externalPage->custom_fields) {
                 foreach ($externalPage->custom_fields as $field) {
                    if (isset($data[$field])) {
                        $queryParams[$field] = $data[$field];
                    }
                }
            }
            
            // If we have params to append
            if (!empty($queryParams)) {
                $separator = (parse_url($url, PHP_URL_QUERY) == NULL) ? '?' : '&';
                $url .= $separator . http_build_query($queryParams);
            }

            return redirect()->away($url);
        }

        // Proxy Mode (Content Replacement)
        try {
            $response = Http::timeout(10)->get($externalPage->url);
            
            if ($response->failed()) {
                abort(502, 'Failed to fetch external page.');
            }

            $content = $response->body();

            // Replace placeholders: [[key]] -> value
            // We use simple string replacement
            
            // 1. Shared Fields
             if ($externalPage->shared_fields) {
                foreach ($externalPage->shared_fields as $field) {
                    $value = $data[$field] ?? '';
                    $content = str_replace("[[$field]]", $value, $content);
                }
            }
            
            // 2. Custom Fields
            if ($externalPage->custom_fields) {
                foreach ($externalPage->custom_fields as $field) {
                    $value = $data[$field] ?? '';
                     $content = str_replace("[[$field]]", $value, $content);
                }
            }
            
            // 3. Fallback for common fields if they are in request but not explicitly in list? 
            // Better stick to the configuration for now.

            // Fix relative links (Basic implementation)
            // This is complex, but for simple use cases:
            // <img src="/foo.jpg"> -> <img src="https://domain.com/foo.jpg">
            $baseUrl = $externalPage->url;
            $parsedUrl = parse_url($baseUrl);
            $rootUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
            if(isset($parsedUrl['port'])) $rootUrl .= ':' . $parsedUrl['port'];

            // Replace src="/..." with src="$rootUrl/..."
            $content = preg_replace('/src="\/(?!\/)/', 'src="' . $rootUrl . '/', $content);
            $content = preg_replace('/href="\/(?!\/)/', 'href="' . $rootUrl . '/', $content);
            $content = preg_replace('/action="\/(?!\/)/', 'action="' . $rootUrl . '/', $content);
            // Replace src="./..." with src="$baseUrlDir/..." if needed, but let's stick to absolute/root-relative for now.
            
            return response($content)->header('Content-Type', $response->header('Content-Type'));

        } catch (\Exception $e) {
             abort(502, 'Error fetching external page: ' . $e->getMessage());
        }
    }
}
