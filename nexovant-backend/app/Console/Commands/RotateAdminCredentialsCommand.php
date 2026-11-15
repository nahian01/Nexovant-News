<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RotateAdminCredentialsCommand extends Command
{
    protected $signature = 'auth:rotate-admin-credentials {--email= : The email address of the administrator account to rotate}';
    protected $description = 'Rotates administrator credentials securely, revokes all tokens and active sessions, and logs an audit event without echoing secrets.';

    public function handle(): int
    {
        $targetEmail = $this->option('email');

        $query = User::query()->where(function ($q) {
            $q->where('account_type', 'admin')
              ->orWhere('account_type', 'chief_admin')
              ->orWhere('account_type', 'super_admin')
              ->orWhereHas('roles', function ($rq) {
                  $rq->whereIn('name', ['admin', 'super_admin', 'chief_admin']);
              });
        });

        if ($targetEmail) {
            $query->where('email', $targetEmail);
        }

        $admins = $query->get();

        if ($admins->isEmpty()) {
            $this->warn('No matching administrator accounts found for rotation.');
            return self::SUCCESS;
        }

        foreach ($admins as $admin) {
            // Generate high-entropy 40-character cryptographically secure replacement
            $newPassword = Str::random(40);

            DB::transaction(function () use ($admin, $newPassword) {
                // 1. Rotate password
                $admin->update([
                    'password' => Hash::make($newPassword),
                ]);

                // 2. Revoke all Sanctum personal access tokens
                $tokenCount = $admin->tokens()->count();
                $admin->tokens()->delete();

                // 3. Invalidate active database sessions if table exists
                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $admin->id)->delete();
                }

                // 4. Record security audit log
                AuditLog::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $admin->id,
                    'action' => 'security.credential.rotated',
                    'resource_type' => 'User',
                    'resource_id' => $admin->id,
                    'metadata' => [
                        'reason' => 'Emergency credential rotation and session invalidation',
                        'revoked_tokens_count' => $tokenCount,
                        'rotated_at' => now()->toIso8601String(),
                        'ip_address' => '127.0.0.1 (CLI)',
                    ],
                    'created_at' => now(),
                ]);
            });

            $this->info("Administrator [{$admin->email}] credential rotated successfully. All active tokens and sessions revoked.");
        }

        return self::SUCCESS;
    }
}
