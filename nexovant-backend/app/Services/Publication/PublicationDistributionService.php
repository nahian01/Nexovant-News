<?php

declare(strict_types=1);

namespace App\Services\Publication;

use App\Models\Publication;
use App\Models\PublicationDownloadLog;
use App\Models\PublicationDownloadToken;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublicationDistributionService
{
    /**
     * Generate an HMAC-signed download token for authorized distribution.
     *
     * @return array<string, mixed>
     */
    public function generateDownloadToken(
        Publication $publication,
        ?User $user = null,
        int $ttlMinutes = 60,
        int $maxDownloads = 5
    ): array {
        // Enforce server-side entitlement check
        if (! $publication->canBeDownloadedBy($user)) {
            throw new AccessDeniedHttpException('Subscription entitlement required to access this executive publication.');
        }

        $rawSecret = Str::random(32);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', "{$publication->id}:" . ($user?->id ?? 'guest') . ":{$timestamp}:{$rawSecret}", config('app.key'));
        $rawToken = "{$timestamp}.{$rawSecret}.{$signature}";
        $tokenHash = hash('sha256', $rawToken);

        $expiresAt = now()->addMinutes($ttlMinutes);

        $tokenRecord = PublicationDownloadToken::create([
            'publication_id' => $publication->id,
            'user_id' => $user?->id,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'is_revoked' => false,
            'max_downloads' => $maxDownloads,
            'download_count' => 0,
        ]);

        $downloadUrl = url("/api/v1/publications/{$publication->id}/stream?token=" . urlencode($rawToken));

        return [
            'token_id' => $tokenRecord->id,
            'token' => $rawToken,
            'download_url' => $downloadUrl,
            'expires_at' => $expiresAt->toIso8601String(),
            'max_downloads' => $maxDownloads,
            'publication' => [
                'id' => $publication->id,
                'title' => $publication->title,
                'edition_number' => $publication->edition_number,
                'file_size_bytes' => $publication->file_size_bytes,
            ],
        ];
    }

    /**
     * Verify download token and record access log.
     */
    public function verifyAndLogDownload(
        string $publicationId,
        string $rawToken,
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): Publication {
        $publication = Publication::findOrFail($publicationId);

        $tokenHash = hash('sha256', $rawToken);
        $tokenRecord = PublicationDownloadToken::where('publication_id', $publication->id)
            ->where('token_hash', $tokenHash)
            ->first();

        if (! $tokenRecord || ! $tokenRecord->isValid()) {
            throw new AccessDeniedHttpException('Download token is invalid, expired, or has reached maximum download quota.');
        }

        // Validate HMAC integrity
        $parts = explode('.', $rawToken);
        if (count($parts) !== 3) {
            throw new AccessDeniedHttpException('Malformed authentication token.');
        }
        [$timestamp, $rawSecret, $signature] = $parts;
        $expectedSignature = hash_hmac('sha256', "{$publication->id}:" . ($tokenRecord->user_id ?? 'guest') . ":{$timestamp}:{$rawSecret}", config('app.key'));

        if (! hash_equals($expectedSignature, $signature)) {
            throw new AccessDeniedHttpException('Token HMAC signature verification failed.');
        }

        // Enforce user ownership if token was issued to a specific user
        if ($tokenRecord->user_id !== null && $user !== null && $tokenRecord->user_id !== $user->id && ! $user->isAdmin()) {
            throw new AccessDeniedHttpException('Token belongs to another subscriber.');
        }

        // Record usage
        $tokenRecord->increment('download_count');

        PublicationDownloadLog::create([
            'publication_id' => $publication->id,
            'user_id' => $user?->id ?? $tokenRecord->user_id,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'downloaded_at' => now(),
        ]);

        return $publication;
    }

    /**
     * Revoke a single download token.
     */
    public function revokeToken(string $tokenId): bool
    {
        $token = PublicationDownloadToken::find($tokenId);
        if (! $token) {
            return false;
        }
        $token->update(['is_revoked' => true]);
        return true;
    }

    /**
     * Revoke all tokens for a given publication.
     */
    public function revokePublicationTokens(string $publicationId): int
    {
        return PublicationDownloadToken::where('publication_id', $publicationId)
            ->update(['is_revoked' => true]);
    }
}
