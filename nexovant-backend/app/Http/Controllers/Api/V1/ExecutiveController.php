<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Executive;
use App\Models\FollowedExecutive;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExecutiveController
{
    /**
     * List verified global business executives.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Executive::with('companies:id,name,ticker,sector')
            ->orderBy('full_name');

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('current_title', 'like', "%{$search}%");
            });
        }

        $executives = $query->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $executives->items(),
            'meta' => [
                'current_page' => $executives->currentPage(),
                'last_page' => $executives->lastPage(),
                'total' => $executives->total(),
            ]
        ]);
    }

    /**
     * Show executive profile and company associations.
     */
    public function show(string $slug, Request $request): JsonResponse
    {
        $executive = Executive::with('companies')->where('slug', $slug)->first();

        if (!$executive) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'EXECUTIVE_NOT_FOUND',
                    'message' => 'The specified executive profile could not be found.',
                ]
            ], 404);
        }

        $isFollowed = false;
        if ($user = $request->user()) {
            $isFollowed = FollowedExecutive::where('user_id', $user->id)
                ->where('executive_id', $executive->id)
                ->exists();
        }

        return response()->json([
            'success' => true,
            'data' => $executive,
            'meta' => [
                'is_followed' => $isFollowed,
            ]
        ]);
    }

    /**
     * Toggle follow state on executive movements.
     */
    public function toggleFollow(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $executive = Executive::findOrFail($id);

        $existing = FollowedExecutive::where('user_id', $user->id)
            ->where('executive_id', $executive->id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json([
                'success' => true,
                'message' => 'Unfollowed executive successfully.',
                'is_followed' => false,
            ]);
        }

        FollowedExecutive::create([
            'user_id' => $user->id,
            'executive_id' => $executive->id,
            'notify_on_movement' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Executive added to leadership alert watchlist.',
            'is_followed' => true,
        ]);
    }
}
