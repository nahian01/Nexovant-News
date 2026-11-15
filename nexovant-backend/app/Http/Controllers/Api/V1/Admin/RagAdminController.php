<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\KnowledgeDocument;
use App\Services\RAG\KnowledgeBaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RagAdminController
{
    public function __construct(
        protected KnowledgeBaseService $knowledgeService
    ) {}

    /**
     * Get RAG knowledge stats and indexed documents list.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant_id');
        $stats = $this->knowledgeService->getTelemetryStats($tenantId);

        $query = KnowledgeDocument::with(['organization:id,name,slug'])
            ->withCount('chunks');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $docs = $query->latest()->get();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'documents' => $docs,
        ]);
    }

    /**
     * Ingest a new document into the knowledge base, split into chunks, and vectorize.
     */
    public function ingest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'in:article,executive_bio,regulatory_filing,earnings_transcript,technical_paper,financial_disclosure,patent_intelligence'],
            'content' => ['required', 'string', 'min:10'],
            'source_url' => ['nullable', 'url', 'max:500'],
            'tenant_id' => ['nullable', 'uuid', 'exists:organizations,id'],
        ]);

        $document = $this->knowledgeService->ingestDocument(
            title: $validated['title'],
            content: $validated['content'],
            documentType: $validated['document_type'],
            tenantId: $validated['tenant_id'] ?? null,
            sourceUrl: $validated['source_url'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => "Document '{$document->title}' successfully ingested and segmented into {$document->chunk_count} vector chunks.",
            'document' => $document,
        ], 201);
    }

    /**
     * Get chunk breakdown for a specific document.
     */
    public function documentChunks(string $id): JsonResponse
    {
        $document = KnowledgeDocument::with(['chunks' => function ($q) {
            $q->orderBy('chunk_index');
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'document' => $document,
            'chunks' => $document->chunks,
        ]);
    }

    /**
     * Trigger re-indexing for a specific document.
     */
    public function reindex(string $id, Request $request): JsonResponse
    {
        $freshContent = $request->input('content');
        $document = $this->knowledgeService->reindexDocument($id, $freshContent);

        return response()->json([
            'success' => true,
            'message' => "Document '{$document->title}' re-indexed successfully. Generated {$document->chunk_count} chunks.",
            'document' => $document,
        ]);
    }

    /**
     * Clear semantic cache entries.
     */
    public function clearCache(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $purged = $this->knowledgeService->clearCache($tenantId);

        return response()->json([
            'success' => true,
            'message' => "Semantic RAG cache purged successfully ({$purged} entries removed).",
            'purged_count' => $purged,
        ]);
    }

    /**
     * Delete a knowledge document and its associated chunks and embeddings.
     */
    public function destroy(string $id): JsonResponse
    {
        $deleted = $this->knowledgeService->deleteDocument($id);

        if (! $deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found or could not be removed.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Document and associated vector chunks removed successfully.',
        ]);
    }
}
