<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Publication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicationController
{
    /**
     * List executive briefings and reports.
     */
    public function index(Request $request): JsonResponse
    {
        $publications = Publication::where('is_public', true)
            ->latest('publication_date')
            ->paginate((int) $request->query('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $publications->items(),
            'meta' => [
                'current_page' => $publications->currentPage(),
                'last_page' => $publications->lastPage(),
                'total' => $publications->total(),
            ]
        ]);
    }

    /**
     * Generate signed download URL for private R2 PDF document.
     */
    public function download(string $id, Request $request): JsonResponse
    {
        $publication = Publication::findOrFail($id);

        // Generate signed URL (simulated with HMAC token for secure private delivery)
        $token = hash_hmac('sha256', $publication->id . now()->timestamp, config('app.key'));
        $downloadUrl = url("/api/v1/publications/{$publication->id}/stream?token={$token}&expires=" . now()->addMinutes(15)->timestamp);

        return response()->json([
            'success' => true,
            'download_url' => $downloadUrl,
            'expires_in_seconds' => 900,
            'publication' => $publication,
        ]);
    }
}
