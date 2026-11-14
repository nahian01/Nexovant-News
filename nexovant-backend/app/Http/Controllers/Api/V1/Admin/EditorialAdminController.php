<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Article;
use App\Models\ArticleAssessment;
use App\Models\ArticleRevision;
use App\Models\ArticleSummary;
use App\Models\AuditLog;
use App\Models\NewsSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EditorialAdminController
{
    /**
     * List all articles with full lifecycle filters and search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Article::with(['source:id,name,credibility_score', 'categories:id,name,slug,color', 'companies:id,name,ticker'])
            ->latest('published_at');

        // Status filter: DRAFT, REVIEW, SCHEDULED, PUBLISHED, UNPUBLISHED, ARCHIVED
        if ($status = $request->query('status')) {
            $query->where('status', strtoupper($status));
        }

        // Verification status filter
        if ($verification = $request->query('verification_status')) {
            $query->where('verification_status', $verification);
        }

        // Search headline or normalized content
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content_normalized', 'like', "%{$search}%");
            });
        }

        if ($request->has('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->query('category_id'));
            });
        }

        $articles = $query->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $articles->items(),
            'meta' => [
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'total' => $articles->total(),
            ]
        ]);
    }

    /**
     * Show comprehensive article dossier with assessment, summary, locks, and revisions.
     */
    public function show(string $id): JsonResponse
    {
        $article = Article::with([
            'source',
            'categories',
            'topics',
            'tags',
            'companies.executives',
            'summary',
            'assessment',
            'revisions.user:id,name,email',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $article,
        ]);
    }

    /**
     * Create a new article manually with 5 manual override locks.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'url'],
            'source_id' => ['nullable', 'exists:news_sources,id'],
            'content_raw' => ['required', 'string'],
            'content_normalized' => ['nullable', 'string'],
            'status' => ['required', 'in:DRAFT,REVIEW,SCHEDULED,PUBLISHED,UNPUBLISHED,ARCHIVED'],
            'scheduled_at' => ['nullable', 'date'],
            'published_at' => ['nullable', 'date'],
            'importance_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'verification_status' => ['nullable', 'in:confirmed,reported_reliable,developing,disputed,unverified,retracted'],
            'is_breaking' => ['nullable', 'boolean'],
            'is_editorial_approved' => ['nullable', 'boolean'],
            'featured_image' => ['nullable', 'string'],
            'featured_image_caption' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string'],
            'meta_keywords' => ['nullable', 'string'],
            'category_ids' => ['nullable', 'array'],
            'topic_ids' => ['nullable', 'array'],
            'tag_names' => ['nullable', 'array'],
            'company_ids' => ['nullable', 'array'],
            // 5 Manual Override Locks
            'lock_category' => ['nullable', 'boolean'],
            'lock_topic' => ['nullable', 'boolean'],
            'lock_content' => ['nullable', 'boolean'],
            'lock_image' => ['nullable', 'boolean'],
            'lock_tags' => ['nullable', 'boolean'],
            // Summary & Assessment
            'executive_summary' => ['nullable', 'string'],
            'key_takeaways' => ['nullable', 'array'],
            'what_happened' => ['nullable', 'string'],
            'why_happened' => ['nullable', 'string'],
            'business_significance' => ['nullable', 'string'],
            'what_to_monitor_next' => ['nullable', 'string'],
        ]);

        $admin = $request->user();
        $sourceId = $validated['source_id'] ?? NewsSource::first()?->id;

        $slug = Str::slug($validated['title']) . '-' . Str::random(5);
        $contentHash = hash('sha256', $validated['content_raw'] . time());

        $status = $validated['status'];
        $isApproved = $status === 'PUBLISHED' ? true : ($validated['is_editorial_approved'] ?? false);

        $article = Article::create([
            'source_id' => $sourceId,
            'title' => $validated['title'],
            'slug' => $slug,
            'canonical_url' => $validated['canonical_url'] ?? url("/news/{$slug}"),
            'content_raw' => $validated['content_raw'],
            'content_normalized' => $validated['content_normalized'] ?? $validated['content_raw'],
            'content_hash' => $contentHash,
            'status' => $status,
            'published_at' => $validated['published_at'] ?? now(),
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'verification_status' => $validated['verification_status'] ?? 'confirmed',
            'importance_score' => $validated['importance_score'] ?? 7.5,
            'is_breaking' => $validated['is_breaking'] ?? false,
            'is_editorial_approved' => $isApproved,
            'featured_image' => $validated['featured_image'] ?? null,
            'featured_image_caption' => $validated['featured_image_caption'] ?? null,
            'meta_title' => $validated['meta_title'] ?? $validated['title'],
            'meta_description' => $validated['meta_description'] ?? null,
            'meta_keywords' => $validated['meta_keywords'] ?? null,
            'lock_category' => $validated['lock_category'] ?? true,
            'lock_topic' => $validated['lock_topic'] ?? true,
            'lock_content' => $validated['lock_content'] ?? true,
            'lock_image' => $validated['lock_image'] ?? true,
            'lock_tags' => $validated['lock_tags'] ?? true,
        ]);

        // Attach categories, topics, companies
        if (!empty($validated['category_ids'])) {
            $article->categories()->sync($validated['category_ids']);
        }
        if (!empty($validated['topic_ids'])) {
            $article->topics()->sync($validated['topic_ids']);
        }
        if (!empty($validated['company_ids'])) {
            $article->companies()->sync($validated['company_ids']);
        }

        // Summary
        if (!empty($validated['executive_summary'])) {
            ArticleSummary::create([
                'article_id' => $article->id,
                'executive_summary' => $validated['executive_summary'],
                'key_takeaways' => $validated['key_takeaways'] ?? [],
                'model_version' => 'manual_editorial',
            ]);
        }

        // Assessment
        if (!empty($validated['what_happened'])) {
            ArticleAssessment::create([
                'article_id' => $article->id,
                'what_happened' => $validated['what_happened'],
                'when_happened' => now()->toIso8601String(),
                'why_happened' => $validated['why_happened'] ?? null,
                'business_significance' => $validated['business_significance'] ?? 'Material business development.',
                'what_to_monitor_next' => $validated['what_to_monitor_next'] ?? 'Further corporate announcements.',
                'confidence_score' => 1.00,
            ]);
        }

        // Initial Revision
        ArticleRevision::create([
            'article_id' => $article->id,
            'user_id' => $admin->id,
            'version_number' => 1,
            'title' => $article->title,
            'content_raw' => $article->content_raw,
            'content_normalized' => $article->content_normalized,
            'summary' => $validated['executive_summary'] ?? null,
            'change_summary' => 'Initial article draft established.',
            'created_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'article.created',
            'resource_type' => 'Article',
            'resource_id' => $article->id,
            'new_values' => ['title' => $article->title, 'status' => $article->status],
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Article '{$article->title}' created successfully.",
            'article' => $article->load(['categories', 'source', 'summary', 'assessment']),
        ], 201);
    }

    /**
     * Update an article, enforcing manual override locks and storing a new revision.
     */
    public function update(string $id, Request $request): JsonResponse
    {
        $article = Article::findOrFail($id);
        $admin = $request->user();

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'content_raw' => ['sometimes', 'string'],
            'content_normalized' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:DRAFT,REVIEW,SCHEDULED,PUBLISHED,UNPUBLISHED,ARCHIVED'],
            'scheduled_at' => ['nullable', 'date'],
            'published_at' => ['nullable', 'date'],
            'importance_score' => ['sometimes', 'numeric', 'min:0', 'max:10'],
            'verification_status' => ['sometimes', 'in:confirmed,reported_reliable,developing,disputed,unverified,retracted'],
            'is_breaking' => ['sometimes', 'boolean'],
            'is_editorial_approved' => ['sometimes', 'boolean'],
            'featured_image' => ['nullable', 'string'],
            'featured_image_caption' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string'],
            'lock_category' => ['sometimes', 'boolean'],
            'lock_topic' => ['sometimes', 'boolean'],
            'lock_content' => ['sometimes', 'boolean'],
            'lock_image' => ['sometimes', 'boolean'],
            'lock_tags' => ['sometimes', 'boolean'],
            'change_summary' => ['nullable', 'string', 'max:255'],
        ]);

        $previousContent = $article->content_raw;
        $contentChanged = isset($validated['content_raw']) && $validated['content_raw'] !== $previousContent;

        $article->update($validated);

        // If content was modified, record a new revision
        if ($contentChanged || isset($validated['title'])) {
            $latestVersion = ArticleRevision::where('article_id', $article->id)->max('version_number') ?? 0;
            ArticleRevision::create([
                'article_id' => $article->id,
                'user_id' => $admin->id,
                'version_number' => $latestVersion + 1,
                'title' => $article->title,
                'content_raw' => $article->content_raw,
                'content_normalized' => $article->content_normalized,
                'summary' => $article->summary?->executive_summary,
                'change_summary' => $validated['change_summary'] ?? 'Editorial revision saved.',
                'created_at' => now(),
            ]);
        }

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'article.updated',
            'resource_type' => 'Article',
            'resource_id' => $article->id,
            'new_values' => $validated,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Article '{$article->title}' updated successfully.",
            'article' => $article->load(['categories', 'source', 'summary', 'assessment']),
        ]);
    }

    /**
     * Update editorial verdict (supporting both frontend action format and verification format).
     */
    public function review(string $id, Request $request): JsonResponse
    {
        $admin = $request->user();
        $article = Article::findOrFail($id);

        // Support frontend action: 'approve' | 'reject'
        if ($request->has('action')) {
            $action = $request->input('action');
            $isApproved = ($action === 'approve');
            $newStatus = $isApproved ? 'PUBLISHED' : 'UNPUBLISHED';
            $verificationStatus = $isApproved ? 'confirmed' : 'disputed';

            $article->update([
                'is_editorial_approved' => $isApproved,
                'status' => $newStatus,
                'verification_status' => $verificationStatus,
            ]);

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => "article.{$action}",
                'resource_type' => 'Article',
                'resource_id' => $article->id,
                'new_values' => ['action' => $action, 'status' => $newStatus],
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => $isApproved ? "Article '{$article->title}' approved and published." : "Article unpublished.",
                'article' => $article->load(['source', 'categories']),
            ]);
        }

        // Standard verification status form
        $request->validate([
            'verification_status' => ['required', 'in:confirmed,reported_reliable,developing,disputed,unverified,retracted'],
            'is_editorial_approved' => ['required', 'boolean'],
            'editorial_notes' => ['nullable', 'string'],
        ]);

        $prevStatus = $article->verification_status;
        $isApproved = (bool) $request->input('is_editorial_approved');

        $article->update([
            'verification_status' => $request->input('verification_status'),
            'is_editorial_approved' => $isApproved,
            'status' => $isApproved ? 'PUBLISHED' : 'UNPUBLISHED',
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'article.editorial_review',
            'resource_type' => 'Article',
            'resource_id' => $article->id,
            'old_values' => ['verification_status' => $prevStatus],
            'new_values' => $request->only('verification_status', 'is_editorial_approved'),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Editorial assessment updated.',
            'article' => $article->load(['source', 'categories']),
        ]);
    }

    /**
     * List all revisions for an article.
     */
    public function revisions(string $id): JsonResponse
    {
        $article = Article::findOrFail($id);
        $revisions = ArticleRevision::with('user:id,name,email')
            ->where('article_id', $article->id)
            ->orderByDesc('version_number')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $revisions,
        ]);
    }

    /**
     * Restore article content from a previous revision.
     */
    public function restoreRevision(string $id, int $version, Request $request): JsonResponse
    {
        $admin = $request->user();
        $article = Article::findOrFail($id);
        $revision = ArticleRevision::where('article_id', $article->id)
            ->where('version_number', $version)
            ->firstOrFail();

        $article->update([
            'title' => $revision->title,
            'content_raw' => $revision->content_raw,
            'content_normalized' => $revision->content_normalized,
        ]);

        // Record that restoration took place as a new revision
        $latestVersion = ArticleRevision::where('article_id', $article->id)->max('version_number') ?? 0;
        ArticleRevision::create([
            'article_id' => $article->id,
            'user_id' => $admin->id,
            'version_number' => $latestVersion + 1,
            'title' => $revision->title,
            'content_raw' => $revision->content_raw,
            'content_normalized' => $revision->content_normalized,
            'change_summary' => "Restored from version #{$version}.",
            'created_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'article.revision_restored',
            'resource_type' => 'Article',
            'resource_id' => $article->id,
            'new_values' => ['restored_from_version' => $version],
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Article restored to revision #{$version}.",
            'article' => $article->load(['source', 'categories']),
        ]);
    }

    /**
     * Soft delete an article.
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        $admin = $request->user();
        $article = Article::findOrFail($id);
        $title = $article->title;

        $article->update(['status' => 'ARCHIVED']);
        $article->delete();

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'article.archived_and_deleted',
            'resource_type' => 'Article',
            'resource_id' => $id,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Article '{$title}' moved to archive and soft-deleted.",
        ]);
    }
}
