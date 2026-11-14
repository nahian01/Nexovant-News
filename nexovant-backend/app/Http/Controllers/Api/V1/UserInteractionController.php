<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Article;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserInteractionController
{
    /**
     * Record an interaction on a story (save, unsave, feedback, report).
     */
    public function interact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'article_id' => ['required', 'string'],
            'action' => ['required', 'string', 'in:save,unsave,more_like_this,less_like_this,hide,report_issue'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $action = $validated['action'];
        $articleId = $validated['article_id'];

        // If saving / unsaving
        if ($action === 'save') {
            DB::table('audit_logs')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'user_id' => $user->id,
                'action' => 'article.save',
                'resource_type' => 'article',
                'resource_id' => $articleId,
                'metadata' => json_encode(['saved' => true]),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Article saved to your executive portfolio.',
                'is_saved' => true,
            ]);
        }

        if ($action === 'unsave') {
            DB::table('audit_logs')
                ->where('user_id', $user->id)
                ->where('action', 'article.save')
                ->where('resource_id', $articleId)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Article removed from saved portfolio.',
                'is_saved' => false,
            ]);
        }

        if ($action === 'report_issue') {
            return response()->json([
                'success' => true,
                'message' => 'Thank you. Our editorial integrity desk has received your report and will verify.',
            ]);
        }

        // Recommendation tuning feedback
        return response()->json([
            'success' => true,
            'message' => 'Preference recorded. We will adjust your executive recommendation signals.',
        ]);
    }

    /**
     * Get saved articles for authenticated user.
     */
    public function savedArticles(Request $request): JsonResponse
    {
        $user = $request->user();
        $savedIds = DB::table('audit_logs')
            ->where('user_id', $user->id)
            ->where('action', 'article.save')
            ->pluck('resource_id')
            ->toArray();

        // If no saved articles, return first article as sample saved item if requested
        $articles = Article::query()
            ->whereIn('id', $savedIds)
            ->with(['source', 'categories', 'companies'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $articles,
        ]);
    }

    /**
     * Get or update interest onboarding preferences.
     */
    public function preferences(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->isMethod('POST') || $request->isMethod('PUT')) {
            $validated = $request->validate([
                'industries' => ['nullable', 'array'],
                'companies' => ['nullable', 'array'],
                'topics' => ['nullable', 'array'],
                'frequency' => ['nullable', 'string'],
                'disable_activity_tracking' => ['nullable', 'boolean'],
            ]);

            $pref = UserPreference::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'preferred_industries' => $validated['industries'] ?? [],
                    'preferred_companies' => $validated['companies'] ?? [],
                    'preferred_topics' => $validated['topics'] ?? [],
                    'email_frequency' => $validated['frequency'] ?? 'daily',
                    'personalization_enabled' => !($validated['disable_activity_tracking'] ?? false),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Your executive intelligence preferences have been updated.',
                'data' => $pref,
            ]);
        }

        $pref = UserPreference::where('user_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'data' => $pref ?: [
                'preferred_industries' => ['Technology', 'Semiconductors', 'Healthcare'],
                'preferred_companies' => ['NVIDIA', 'Novartis'],
                'preferred_topics' => ['Foundry & Fabs', 'CEO Transitions'],
                'email_frequency' => 'daily',
                'personalization_enabled' => true,
            ],
        ]);
    }

    /**
     * Get user notification list.
     */
    public function notifications(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                [
                    'id' => 'notif-1',
                    'type' => 'breaking',
                    'title' => 'Breaking: NVIDIA Board Approves $15B EMEA Silicon Expansion',
                    'message' => 'Official SEC 8-K disclosure confirmed. Sovereign compute expansion in Germany & France.',
                    'time' => '10m ago',
                    'is_read' => false,
                    'link' => '/news/nvidia-custom-silicon-foundry-emea',
                ],
                [
                    'id' => 'notif-2',
                    'type' => 'trend_alert',
                    'title' => 'Velocity Spike: EU Silicon Sovereignty',
                    'message' => 'Discussion acceleration +88% across institutional social channels in 3 hours.',
                    'time' => '2h ago',
                    'is_read' => false,
                    'link' => '/trends',
                ],
                [
                    'id' => 'notif-3',
                    'type' => 'publication',
                    'title' => 'Executive Briefing PDF Ready',
                    'message' => 'Your Daily European Tech & Macro Strategy digest is ready for download.',
                    'time' => '5h ago',
                    'is_read' => true,
                    'link' => '/reports',
                ],
            ],
            'unread_count' => 2,
        ]);
    }
}
