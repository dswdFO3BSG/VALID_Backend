<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SecurityTestController extends Controller
{
    /**
     * Test endpoint to verify CSP headers
     */
    public function testCspHeaders(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'CSP headers should be present in this response',
            'timestamp' => now()->toISOString(),
            'headers_info' => [
                'csp_enabled' => true,
                'script_nonce' => csp_script_nonce(),
                'style_nonce' => csp_style_nonce(),
                'nonce_enabled' => config('csp.nonce.enabled', false)
            ]
        ]);
    }

    /**
     * Test endpoint for CSP violation reporting
     */
    public function reportCspViolation(Request $request): JsonResponse
    {
        // Log CSP violations for monitoring
        Log::warning('CSP Violation Report', [
            'violation' => $request->all(),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'timestamp' => now()
        ]);

        return response()->json(['status' => 'violation logged'], 200);
    }
}
