<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN_ADMIN_ACCESS',
                    'message' => 'Access denied. Administrator privileges are required.',
                ]
            ], 403);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_SUSPENDED',
                    'message' => 'Your administrative account has been deactivated.',
                ]
            ], 403);
        }

        return $next($request);
    }
}
