<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'permission' => \App\Http\Middleware\EnsureUserHasPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Enforce consistent JSON error structure per RFC 7807 & standard test helpers
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The provided data failed validation.',
                    'errors' => $e->errors(),
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The provided data failed validation.',
                        'details' => $e->errors(),
                    ],
                    'meta' => [
                        'timestamp' => now()->toIso8601String(),
                    ]
                ], 422);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHENTICATED',
                        'message' => 'Unauthenticated access. Please log in.',
                    ],
                    'meta' => [
                        'timestamp' => now()->toIso8601String(),
                    ]
                ], 401);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => $e->getMessage() ?: 'Forbidden: Insufficient entitlements.',
                    ],
                    'meta' => [
                        'timestamp' => now()->toIso8601String(),
                    ]
                ], 403);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'RESOURCE_NOT_FOUND',
                        'message' => 'The requested resource could not be found.',
                    ],
                    'meta' => [
                        'timestamp' => now()->toIso8601String(),
                    ]
                ], 404);
            }
        });
    })->create();
