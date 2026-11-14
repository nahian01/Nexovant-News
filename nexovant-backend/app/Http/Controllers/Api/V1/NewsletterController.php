<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewsletterController
{
    /**
     * Subscribe an email to the public executive intelligence briefings & claim offer.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
            'frequency' => ['nullable', 'string', 'in:daily,weekly,breaking'],
            'source' => ['nullable', 'string', 'max:100'],
            'offer_code' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide a valid corporate or professional email address.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = strtolower(trim($request->input('email')));
        $frequency = $request->input('frequency', 'daily');
        $source = $request->input('source', 'public_blog');
        $offerCode = $request->input('offer_code', 'NEXO-EXECUTIVE-30');

        $subscriber = NewsletterSubscriber::updateOrCreate(
            ['email' => $email],
            [
                'frequency' => $frequency,
                'source' => $source,
                'offer_code' => $offerCode,
                'is_active' => true,
                'ip_address' => $request->ip(),
                'subscribed_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'You are subscribed to Nexovant Executive Intelligence Briefings. Check your email for this morning\'s executive digest.',
            'data' => [
                'email' => $subscriber->email,
                'frequency' => $subscriber->frequency,
                'subscribed_at' => $subscriber->subscribed_at->toIso8601String(),
            ],
            'offer' => [
                'code' => $offerCode,
                'title' => 'Limited Executive Welcome Offer',
                'discount_percent' => 30,
                'description' => '30% Off Annual Executive Access + 14-Day Free Briefing Pass',
                'claim_url' => '/register?offer=' . urlencode($offerCode) . '&email=' . urlencode($email),
            ],
        ], 200);
    }

    /**
     * List subscribers for admin oversight.
     */
    public function index(Request $request): JsonResponse
    {
        $subscribers = NewsletterSubscriber::query()
            ->latest('subscribed_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $subscribers->items(),
            'meta' => [
                'total' => $subscribers->total(),
                'current_page' => $subscribers->currentPage(),
                'last_page' => $subscribers->lastPage(),
            ],
        ]);
    }
}
