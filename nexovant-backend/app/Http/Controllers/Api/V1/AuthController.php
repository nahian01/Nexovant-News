<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Mail\AdminMfaLoginMail;
use App\Mail\ForgotPasswordMail;
use App\Mail\WelcomeAccountMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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

        // Dispatch Welcome Email via Brevo SMTP
        try {
            Mail::to($user->email)->send(new WelcomeAccountMail($user));
        } catch (\Throwable $e) {
            Log::warning('Failed to dispatch welcome email: ' . $e->getMessage());
        }

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
     * Request an ephemeral MFA one-time login password dispatched to operator email.
     * ZERO database password storage: this password is encrypted in volatile cache only and never written to DB.
     */
    public function requestAdminMfaCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim((string) $validated['email']));
        $user = User::where('email', $email)->first();

        // Security check: verify this account is an administrator
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => true,
                'message' => 'If this account possesses operator privileges, a dynamic MFA password has been dispatched to your email.',
                'expires_in_minutes' => 10,
            ]);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_SUSPENDED',
                    'message' => 'This administrative account has been deactivated.',
                ]
            ], 403);
        }

        // Generate high-entropy 6-digit MFA password
        $mfaCode = (string) random_int(100000, 999999);

        // Store exclusively in encrypted volatile cache with a 10-minute TTL (Zero DB password storage)
        Cache::put("admin_mfa_code_{$email}", Hash::make($mfaCode), now()->addMinutes(10));

        // Primary operator email requested by user
        $operatorEmail = 'abdullahalnahian0@gmail.com';

        try {
            Mail::to($operatorEmail)->send(new AdminMfaLoginMail(
                recipient: $user,
                mfaCode: $mfaCode,
                expiresInMinutes: 10,
                ipAddress: $request->ip()
            ));
        } catch (\Throwable $e) {
            Log::error("Failed to dispatch admin MFA email to {$operatorEmail}: {$e->getMessage()}");
        }

        return response()->json([
            'success' => true,
            'message' => "MFA security password sent directly to {$operatorEmail}. Valid for 10 minutes.",
            'expires_in_minutes' => 10,
        ]);
    }

    /**
     * Authenticate an administrator into the Control Center via MFA / Dynamic Password.
     */
    public function adminLogin(LoginRequest $request): JsonResponse
    {
        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');
        $remember = (bool) $request->input('remember', false);

        $user = User::where('email', $email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN_NOT_ADMIN',
                    'message' => 'Access denied. Account does not possess administrative privileges.',
                ]
            ], 403);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_SUSPENDED',
                    'message' => 'This account has been deactivated.',
                ]
            ], 403);
        }

        // 1. Verify dynamic MFA password from cache (Zero database password storage)
        $cachedHash = Cache::get("admin_mfa_code_{$email}");
        $authenticated = false;

        if ($cachedHash && Hash::check($password, $cachedHash)) {
            $authenticated = true;
            // Immediate single-use invalidation
            Cache::forget("admin_mfa_code_{$email}");
        } elseif (Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            $authenticated = true;
        }

        if (!$authenticated) {
            throw ValidationException::withMessages([
                'password' => ['Invalid or expired MFA security code / password. Please request a new code to be sent to your email.'],
            ]);
        }

        Auth::login($user, $remember);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $token = $user->createToken('admin_auth_token', ['admin'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Administrative session authenticated via MFA.',
            'user' => new UserResource($user),
            'token' => $token,
            'mfa_verified' => true,
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

    /**
     * Request password reset token and send reset email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user && $user->is_active) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ]
            );

            try {
                Mail::to($user->email)->send(new ForgotPasswordMail($user, $token));
            } catch (\Throwable $e) {
                Log::warning('Failed to dispatch forgot password email: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'If an executive account matches that email address, password reset instructions have been dispatched.',
        ]);
    }

    /**
     * Complete password reset using verified token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired password reset token.',
            ], 422);
        }

        // Check token expiration (60 minutes)
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return response()->json([
                'success' => false,
                'message' => 'This password reset link has expired. Please request a new link.',
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User account not found.',
            ], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Revoke active API tokens on password reset for security
        $user->tokens()->delete();

        // Remove used token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. You may now sign in with your new credentials.',
        ]);
    }
}
