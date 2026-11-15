<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscriber;

use App\Models\SubscriberInteraction;
use App\Models\UserPreference;
use App\Services\Workspace\PersonalizedFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController
{
    public function __construct(
        protected PersonalizedFeedService $feedService
    ) {}

    /**
     * Get personalized intelligence feed for subscriber.
     */
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        $section = $request->query('section');

        $feed = $this->feedService->getPersonalizedFeed($user, $section);
        $stream = $this->feedService->getFeedStream($user, $request->all());

        return response()->json([
            'success' => true,
            'feed' => $feed,
            'stream' => $stream,
            'data' => $stream['items'],
            'meta' => $stream['meta'],
        ]);
    }

    /**
     * Get comprehensive overview and dashboard metrics for subscriber.
     */
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        $overview = $this->feedService->getSubscriberOverview($user);

        return response()->json([
            'success' => true,
            'overview' => $overview,
        ]);
    }

    /**
     * Get all entities followed by the subscriber.
     */
    public function getFollowing(Request $request): JsonResponse
    {
        $user = $request->user();
        $entities = $this->feedService->getFollowingList($user);

        return response()->json([
            'success' => true,
            'data' => $entities,
            'following' => $entities,
            'total' => count($entities),
        ]);
    }

    /**
     * Follow or unfollow any entity.
     */
    public function toggleFollow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => ['required', 'string', 'in:company,executive,technology,topic,industry'],
            'entity_id' => ['required', 'string'],
            'name' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $result = $this->feedService->toggleFollowEntity(
            $user,
            $validated['entity_type'],
            $validated['entity_id'],
            $validated['name'] ?? null
        );

        return response()->json($result);
    }

    /**
     * Get suggested entities for subscriber to follow.
     */
    public function getFollowingSuggestions(Request $request): JsonResponse
    {
        $user = $request->user();
        $suggestions = $this->feedService->getFollowingSuggestions($user);

        return response()->json([
            'success' => true,
            'data' => $suggestions,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Get intelligence feed filtered to followed entities.
     */
    public function getFollowingFeed(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->feedService->getFollowingFeed($user, $request->all());

        return response()->json([
            'success' => true,
            'data' => $result['items'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * Record an interaction signal (view, save, follow, like, dislike, hide, search).
     */
    public function interact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:view,save,unsave,follow,unfollow,like,dislike,hide,search,report'],
            'target_type' => ['required', 'string'],
            'target_id' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $user = $request->user();
        $interaction = $this->feedService->recordInteraction(
            $user,
            $validated['action'],
            $validated['target_type'],
            $validated['target_id'] ?? null,
            $validated['metadata'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Interaction recorded.',
            'interaction' => $interaction,
        ]);
    }

    /**
     * Get subscriber reading history.
     */
    public function readingHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $history = SubscriberInteraction::where('user_id', $user->id)
            ->whereIn('interaction_type', ['view', 'search'])
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json([
            'success' => true,
            'history' => $history,
        ]);
    }

    /**
     * Clear reading history.
     */
    public function clearHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $this->feedService->clearReadingHistory($user);

        return response()->json([
            'success' => true,
            'message' => 'Reading history cleared.',
            'cleared_count' => $count,
        ]);
    }

    /**
     * Reset personalization signals.
     */
    public function resetSignals(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $this->feedService->resetPersonalizationSignals($user);

        return response()->json([
            'success' => true,
            'message' => 'Personalization signals reset.',
            'reset_count' => $count,
        ]);
    }

    /**
     * Get or update interest preferences.
     */
    public function preferences(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->isMethod('POST') || $request->isMethod('PUT')) {
            $validated = $request->validate([
                'industries' => ['nullable', 'array'],
                'companies' => ['nullable', 'array'],
                'topics' => ['nullable', 'array'],
                'executives' => ['nullable', 'array'],
                'email_frequency' => ['nullable', 'string'],
                'digest_frequency' => ['nullable', 'string'],
                'personalization_enabled' => ['nullable', 'boolean'],
            ]);

            $pref = UserPreference::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'preferred_industries' => $validated['industries'] ?? [],
                    'preferred_companies' => $validated['companies'] ?? [],
                    'preferred_topics' => $validated['topics'] ?? [],
                    'preferred_executives' => $validated['executives'] ?? [],
                    'email_frequency' => $validated['email_frequency'] ?? 'daily',
                    'digest_frequency' => $validated['digest_frequency'] ?? 'daily',
                    'personalization_enabled' => $validated['personalization_enabled'] ?? true,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Preferences updated.',
                'preferences' => $pref,
            ]);
        }

        $pref = UserPreference::firstOrCreate(
            ['user_id' => $user->id],
            [
                'preferred_industries' => ['Technology', 'Semiconductors'],
                'preferred_companies' => ['ASML', 'TSMC', 'NVIDIA'],
                'preferred_topics' => ['Foundry & Fabs', 'Lithography', 'AI Compute'],
                'email_frequency' => 'daily',
                'digest_frequency' => 'daily',
                'personalization_enabled' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'preferences' => $pref,
        ]);
    }
}
