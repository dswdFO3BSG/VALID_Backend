<?php

namespace App\Http\Middleware;

use App\Services\AuditTrailService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuditTrailMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log API requests
        if ($request->is('api/*')) {
            $this->logApiRequest($request, $response);
        }

        return $response;
    }

    /**
     * Log API request to audit trail
     */
    private function logApiRequest(Request $request, Response $response): void
    {
        try {
            // Skip all GET requests as they are read-only operations
            if (strtoupper($request->method()) === 'GET') {
                return;
            }

            // Don't log certain routes to avoid noise
            if ($this->shouldSkipLogging($request->path(), $request->method())) {
                return;
            }

            // Get user empno
            $user = Auth::user();
            $empno = $user ? $user->empno : 'ANONYMOUS';

            // Determine module based on route
            $module = $this->determineModule($request->path());

            // Determine action based on HTTP method
            $action = $this->determineAction($request->method());

            // Get route parameters
            $routeParams = $request->route() ? $request->route()->parameters() : [];

            // Build description
            $description = $this->buildDescription($request, $response, $routeParams);

            AuditTrailService::logCustomAction(
                $empno,
                $action,
                $module,
                $description,
                null, // table_name
                isset($routeParams['id']) ? $routeParams['id'] : null, // record_id
                null, // old_values
                $request->method() === 'POST' ? $request->except(['password', 'password_confirmation', 'token']) : null // new_values
            );

        } catch (\Exception $e) {
            // Don't break the request if audit logging fails
            Log::error('Audit Trail Middleware Error: ' . $e->getMessage());
        }
    }

    /**
     * Determine module based on route path
     */
    private function determineModule(string $path): string
    {
        if (str_contains($path, 'users') || str_contains($path, 'user-access')) {
            return 'user_access';
        }
        
        if (str_contains($path, 'queue')) {
            return 'queue_manager';
        }
        
        if (str_contains($path, 'clients') || str_contains($path, 'reports')) {
            return 'masterlist';
        }
        
        if (str_contains($path, 'mfa') || str_contains($path, 'auth')) {
            return 'authentication';
        }
        
        if (str_contains($path, 'audit-trail')) {
            return 'audit_trail';
        }

        return 'api';
    }

    /**
     * Determine action based on HTTP method
     */
    private function determineAction(string $method): string
    {
        switch (strtoupper($method)) {
            case 'POST':
                return 'API_CREATE';
            case 'PUT':
            case 'PATCH':
                return 'API_UPDATE';
            case 'DELETE':
                return 'API_DELETE';
            case 'GET':
                return 'API_VIEW';
            default:
                return 'API_REQUEST';
        }
    }

    /**
     * Build human readable description
     */
    private function buildDescription(Request $request, Response $response, array $routeParams): string
    {
        $method = strtoupper($request->method());
        $path = $request->path();
        $statusCode = $response->getStatusCode();

        $description = "{$method} request to {$path}";
        
        if (!empty($routeParams)) {
            $params = implode(', ', array_map(fn($k, $v) => "{$k}: {$v}", array_keys($routeParams), $routeParams));
            $description .= " with parameters ({$params})";
        }
        
        $description .= " - Status: {$statusCode}";

        if ($statusCode >= 400) {
            $description .= " (Error)";
        }

        return $description;
    }

    /**
     * Check if we should skip logging for this route
     */
    private function shouldSkipLogging(string $path, string $method = null): bool
    {
        // Routes to skip logging completely (mainly to avoid recursion)
        $skipPaths = [
            'api/audit-trail', // Don't log audit trail requests to avoid recursion
        ];

        // Check skip paths
        foreach ($skipPaths as $skipPath) {
            if (str_contains($path, $skipPath)) {
                return true;
            }
        }

        return false;
    }
}
