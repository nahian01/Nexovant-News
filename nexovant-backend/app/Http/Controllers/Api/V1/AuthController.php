<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController
{
    /**
     * Register a new subscriber account.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'account_type' => $validated['account_type'] ?? 'individual',
            'is_active' => true,
        ]);

        // Automatically authenticate session if stateful session is present
        if ($request->hasSession()) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        // Issue personal access token for client apps
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Account registered successfully.',
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    /**
     * Authenticate an existing subscriber.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');
        $remember = (bool) $request->input('remember', false);

        if (!Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_SUSPENDED',
                    'message' => 'This account has been deactivated. Please contact support.',
                ]
            ], 403);
        }

        // Regenerate session ID if session exists for SPA cookie security
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Authenticated successfully.',
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * Authenticate an administrator into the Control Center.
     */
    public function adminLogin(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');
        $remember = (bool) $request->input('remember', false);

        if (!Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_SUSPENDED',
                    'message' => 'This account has been deactivated.',
                ]
            ], 403);
        }

        if (!$user->isAdmin()) {
            Auth::logout();
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN_NOT_ADMIN',
                    'message' => 'Access denied. Account does not possess administrative privileges.',
                ]
            ], 403);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $token = $user->createToken('admin_auth_token', ['admin'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Administrative session authenticated.',
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * Get the authenticated user's profile.
     */
    public function user(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Terminate the authenticated session.
     */
    public function logout(Request $request): JsonResponse
    {
        if ($user = $request->user()) {
            // Delete current token if authenticated via token
            $user->currentAccessToken()?->delete();
        }

        // Invalidate web session if active
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }
}
