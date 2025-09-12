<?php

// use Illuminate\Foundation\Application;
// use Illuminate\Foundation\Configuration\Exceptions;
// use Illuminate\Foundation\Configuration\Middleware;

// return Application::configure(basePath: dirname(__DIR__))
//     ->withRouting(
//         web: __DIR__.'/../routes/web.php',
//         api: __DIR__.'/../routes/api.php',
//         commands: __DIR__.'/../routes/console.php',
//         health: '/up',
//     )
//     ->withMiddleware(function (Middleware $middleware) {
//         //
//     })
//     ->withExceptions(function (Exceptions $exceptions) {
//         //
//     })->create();

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register audit trail middleware with alias
        $middleware->alias([
            'audit.trail' => \App\Http\Middleware\AuditTrailMiddleware::class,
            'csp' => \App\Http\Middleware\ContentSecurityPolicyMiddleware::class,
        ]);

        // Apply CSP middleware globally to all web routes
        $middleware->web(append: [
            \App\Http\Middleware\ContentSecurityPolicyMiddleware::class,
        ]);

        // Apply CSP middleware to API routes as well
        $middleware->api(append: [
            \App\Http\Middleware\ContentSecurityPolicyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Unauthenticated
        $exceptions->render(function (Illuminate\Auth\AuthenticationException $e, $request) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        });

        // Authorization failed
        $exceptions->render(function (Illuminate\Auth\Access\AuthorizationException $e, $request) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        });

        // Validation failed
        $exceptions->render(function (Illuminate\Validation\ValidationException $e, $request) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => collect($e->errors())->map(function ($messages) {
                    return $messages[0]; // Get only the first message per field
                }),
            ], 422);
        });

        // Model not found
        $exceptions->render(function (Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            return response()->json(['error' => 'Resource not found.'], 404);
        });

        // Route not found
        $exceptions->render(function (Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            return response()->json(['error' => 'Route not found.'], 404);
        });

        // Catch-all fallback
        $exceptions->render(function (Throwable $e, $request) {
            return response()->json([
                'error' => 'Server error.',
                'message' => $e->getMessage()
            ], 500);
        });
    })->create();
