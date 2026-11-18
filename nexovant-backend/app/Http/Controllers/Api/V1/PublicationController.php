<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Mail\ExecutiveReportDeliveryMail;
use App\Models\Publication;
use App\Services\Publication\PdfRenderingService;
use App\Services\Publication\PublicationDistributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublicationController
{
    public function __construct(
        protected PublicationDistributionService $distributor,
        protected PdfRenderingService $pdfRenderer
    ) {}

    /**
     * List executive briefings and reports accessible to user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Publication::with('template:id,name,page_size')
            ->where('status', 'published');

        if (! $user) {
            $query->where('visibility', 'public');
        } elseif (! $user->isAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('visibility', 'public')
                  ->orWhere('visibility', 'registered');

                if ($user->hasRole(['subscriber', 'premium', 'enterprise', 'analyst', 'editor']) || $user->subscription?->isActive()) {
                    $q->orWhere('visibility', 'premium');
                }

                if ($user->hasRole(['enterprise', 'analyst'])) {
                    $q->orWhere('visibility', 'enterprise');
                }
            });
        }

        $publications = $query->latest('publication_date')
            ->paginate((int) $request->query('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $publications->items(),
            'meta' => [
                'current_page' => $publications->currentPage(),
                'last_page' => $publications->lastPage(),
                'total' => $publications->total(),
            ],
        ]);
    }

    /**
     * View single publication details.
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $publication = Publication::with('template')->findOrFail($id);
        $user = $request->user();

        if (! $publication->canBeDownloadedBy($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription entitlement required to access this executive publication.',
                'requires_subscription' => true,
            ], 403);
        }

        return response()->json([
            'success' => true,
            'publication' => $publication,
        ]);
    }

    /**
     * Generate signed download token and URL for secure PDF document.
     */
    public function download(string $id, Request $request): JsonResponse
    {
        $publication = Publication::findOrFail($id);
        $user = $request->user();

        $tokenData = $this->distributor->generateDownloadToken(
            publication: $publication,
            user: $user,
            ttlMinutes: (int) $request->query('ttl', 60),
            maxDownloads: (int) $request->query('max_downloads', 5)
        );

        return response()->json([
            'success' => true,
            'download_url' => $tokenData['download_url'],
            'token' => $tokenData['token'],
            'expires_at' => $tokenData['expires_at'],
            'publication' => $tokenData['publication'],
        ]);
    }

    /**
     * Stream secure private PDF file verified by HMAC token.
     */
    public function stream(string $id, Request $request): BinaryFileResponse|JsonResponse
    {
        $rawToken = (string) $request->query('token');
        if (empty($rawToken)) {
            return response()->json(['success' => false, 'message' => 'Missing security token.'], 401);
        }

        $publication = $this->distributor->verifyAndLogDownload(
            publicationId: $id,
            rawToken: $rawToken,
            user: $request->user(),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        // Ensure PDF is generated on disk
        if (! Storage::disk('local')->exists($publication->file_path)) {
            $this->pdfRenderer->render($publication);
        }

        $absolutePath = Storage::disk('local')->path($publication->file_path);
        if (! file_exists($absolutePath)) {
            throw new NotFoundHttpException('Publication PDF document not found on storage server.');
        }

        return response()->file($absolutePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($publication->file_path) . '"',
        ]);
    }

    /**
     * Dispatch executive publication dossier directly to user's email via Brevo SMTP.
     */
    public function emailReport(string $id, Request $request): JsonResponse
    {
        $publication = Publication::with('template')->findOrFail($id);
        $user = $request->user();

        $recipientEmail = $request->input('recipient_email', $request->input('email', $user?->email));
        if (empty($recipientEmail)) {
            return response()->json([
                'success' => false,
                'message' => 'A valid recipient email address is required.',
            ], 422);
        }

        // Render PDF to disk if missing
        if (! Storage::disk('local')->exists($publication->file_path)) {
            try {
                $this->pdfRenderer->render($publication);
            } catch (\Throwable $e) {
                Log::warning('Could not render PDF before emailing: ' . $e->getMessage());
            }
        }

        $tokenData = $this->distributor->generateDownloadToken(
            publication: $publication,
            user: $user,
            ttlMinutes: 60 * 24 * 7, // 7 days valid
            maxDownloads: 10
        );

        try {
            Mail::to($recipientEmail)->send(new ExecutiveReportDeliveryMail(
                recipient: $user ?? $recipientEmail,
                publication: $publication,
                downloadUrl: $tokenData['download_url'],
                attachmentPath: $publication->file_path
            ));

            return response()->json([
                'success' => true,
                'message' => "Executive intelligence dossier dispatched to {$recipientEmail}.",
                'download_url' => $tokenData['download_url'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send publication report email: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to dispatch report via email. Please check SMTP configuration.',
                'error' => $e->getMessage(),
                'download_url' => $tokenData['download_url'],
            ], 500);
        }
    }
}
