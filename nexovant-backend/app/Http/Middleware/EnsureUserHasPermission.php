<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INSUFFICIENT_PERMISSIONS',
                    'message' => "Access denied. Required permission: {$permission}.",
                ]
            ], 403);
        }

        return $next($request);
    }
}
