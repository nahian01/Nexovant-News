<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\SubscriptionEntitlementOverride;
use App\Models\User;
use Illuminate\Support\Str;

class EntitlementService
{
    /**
     * Standard platform feature keys.
     */
    public const FEATURE_MAX_WATCHLISTS = 'max_watchlists';
    public const FEATURE_MAX_ENTITIES_PER_WATCHLIST = 'max_entities_per_watchlist';
    public const FEATURE_MAX_ACTIVE_MONITORS = 'max_active_monitors';
    public const FEATURE_DAILY_RESEARCH_QUERIES = 'daily_research_queries';
    public const FEATURE_PUBLICATION_ACCESS = 'publication.access';
    public const FEATURE_PDF_DOWNLOAD = 'publication.pdf_download';
    public const FEATURE_AI_DEEP_RESEARCH = 'ai_deep_research';
    public const FEATURE_HISTORICAL_TIMELINE_DAYS = 'historical_timeline_days';
    public const FEATURE_EXPORT_FORMATS = 'export_formats';

    // Stage 15 Executive Decision Support Features
    public const FEATURE_EXECUTIVE_BRIEFINGS = 'executive.briefings';
    public const FEATURE_STRATEGIC_IMPACT = 'executive.strategic_impact';
    public const FEATURE_OPPORTUNITY_RISK = 'executive.opportunity_risk';
    public const FEATURE_SCENARIO_ANALYSIS = 'executive.scenario_analysis';
    public const FEATURE_DECISION_SUPPORT = 'executive.decision_support';
    public const FEATURE_EXECUTIVE_ASSISTANT = 'executive.assistant';
    public const FEATURE_MAX_EXECUTIVE_BRIEFINGS = 'max_executive_briefings';
    public const FEATURE_MAX_SCENARIOS = 'max_scenarios';
    public const FEATURE_MAX_DECISIONS = 'max_decisions';

    /**
     * Check if a boolean feature is granted to the user.
     */
    public function hasFeature(User $user, string $featureKey): bool
    {
        // Admins have unrestricted access
        if ($user->isAdmin()) {
            return true;
        }

        // 1. Check active administrative overrides
        $override = $this->getActiveOverride($user, $featureKey);
        if ($override !== null) {
            return filter_var($override->value, FILTER_VALIDATE_BOOLEAN);
        }

        // 2. Check active subscription plan
        $plan = $this->getUserPlan($user);
        if ($plan) {
            $val = $plan->getEntitlementValue($featureKey);
            if ($val !== null) {
                return (bool) $val;
            }
        }

        // 3. Fallback defaults for free tier
        return match ($featureKey) {
            self::FEATURE_PUBLICATION_ACCESS => true, // Public/Registered publications
            self::FEATURE_PDF_DOWNLOAD => false,
            self::FEATURE_AI_DEEP_RESEARCH => false,
            default => false,
        };
    }

    /**
     * Get numerical limit for a feature. Returns PHP_INT_MAX if unlimited.
     */
    public function getLimit(User $user, string $featureKey, int $defaultLimit = 5): int
    {
        // Admins have unlimited limits
        if ($user->isAdmin()) {
            return PHP_INT_MAX;
        }

        // 1. Check active administrative overrides
        $override = $this->getActiveOverride($user, $featureKey);
        if ($override !== null) {
            return $this->parseLimitValue($override->value);
        }

        // 2. Check active subscription plan
        $plan = $this->getUserPlan($user);
        if ($plan) {
            $val = $plan->getEntitlementValue($featureKey);
            if ($val !== null) {
                return $this->parseLimitValue((string) $val);
            }
        }

        return $defaultLimit;
    }

    /**
     * Check if user is within the numerical limit for a given feature.
     */
    public function checkLimit(User $user, string $featureKey, int $currentCount, int $defaultLimit = 5): bool
    {
        $limit = $this->getLimit($user, $featureKey, $defaultLimit);
        return $currentCount < $limit;
    }

    /**
     * Get remaining quota for a feature.
     */
    public function getRemainingQuota(User $user, string $featureKey, int $currentCount, int $defaultLimit = 5): int
    {
        $limit = $this->getLimit($user, $featureKey, $defaultLimit);
        if ($limit === PHP_INT_MAX) {
            return 999999;
        }

        return max(0, $limit - $currentCount);
    }

    /**
     * Get an entitlement value of any type.
     */
    public function getEntitlement(User $user, string $featureKey, mixed $default = null): mixed
    {
        if ($user->isAdmin()) {
            return 'unlimited';
        }

        $override = $this->getActiveOverride($user, $featureKey);
        if ($override !== null) {
            return $override->value;
        }

        $plan = $this->getUserPlan($user);
        if ($plan) {
            $val = $plan->getEntitlementValue($featureKey);
            if ($val !== null) {
                return $val;
            }
        }

        return $default;
    }

    /**
     * Grant a temporary or permanent administrative entitlement override.
     */
    public function grantOverride(
        User $admin,
        User $targetUser,
        string $featureKey,
        string $value,
        string $reason,
        ?\DateTimeInterface $expiresAt = null
    ): SubscriptionEntitlementOverride {
        $sub = $targetUser->subscription;

        $override = SubscriptionEntitlementOverride::create([
            'id' => (string) Str::uuid(),
            'user_id' => $targetUser->id,
            'subscription_id' => $sub?->id,
            'feature_key' => $featureKey,
            'value' => $value,
            'granted_by_user_id' => $admin->id,
            'reason' => $reason,
            'expires_at' => $expiresAt,
        ]);

        AuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'action' => 'admin.entitlement.override_granted',
            'resource_type' => 'SubscriptionEntitlementOverride',
            'resource_id' => $override->id,
            'metadata' => [
                'target_user_id' => $targetUser->id,
                'feature_key' => $featureKey,
                'value' => $value,
                'reason' => $reason,
                'expires_at' => $expiresAt?->format('c'),
            ],
            'created_at' => now(),
        ]);

        return $override;
    }

    /**
     * Get all active entitlements snapshot for the user workspace.
     */
    public function getEntitlementsSnapshot(User $user): array
    {
        $plan = $this->getUserPlan($user);
        $sub = $user->subscription;
        $org = $user->activeOrganization();
        $isOrgPooled = false;

        if ((! $sub || ! $sub->isActive()) && $org) {
            $orgSub = $org->activeSubscription();
            if ($orgSub && $orgSub->isActive()) {
                $sub = $orgSub;
                $isOrgPooled = true;
            }
        }

        return [
            'plan_name' => $plan?->name ?? 'Free Tier',
            'plan_slug' => $plan?->slug ?? 'free',
            'subscription_status' => $sub?->status ?? 'none',
            'is_active' => $sub ? $sub->isActive() : true,
            'is_organization_pooled' => $isOrgPooled,
            'organization' => $org ? [
                'id' => $org->id,
                'name' => $org->name,
                'role' => $user->organizationRole($org),
                'is_pooled' => $isOrgPooled,
            ] : null,
            'limits' => [
                'max_watchlists' => $this->formatLimitDisplay($this->getLimit($user, self::FEATURE_MAX_WATCHLISTS, 3)),
                'max_entities_per_watchlist' => $this->formatLimitDisplay($this->getLimit($user, self::FEATURE_MAX_ENTITIES_PER_WATCHLIST, 10)),
                'max_active_monitors' => $this->formatLimitDisplay($this->getLimit($user, self::FEATURE_MAX_ACTIVE_MONITORS, 3)),
                'daily_research_queries' => $this->formatLimitDisplay($this->getLimit($user, self::FEATURE_DAILY_RESEARCH_QUERIES, 10)),
                'historical_timeline_days' => $this->formatLimitDisplay($this->getLimit($user, self::FEATURE_HISTORICAL_TIMELINE_DAYS, 90)),
            ],
            'features' => [
                'ai_deep_research' => $this->hasFeature($user, self::FEATURE_AI_DEEP_RESEARCH),
                'pdf_downloads' => $this->hasFeature($user, self::FEATURE_PDF_DOWNLOAD),
                'publication_access' => $this->hasFeature($user, self::FEATURE_PUBLICATION_ACCESS),
            ],
        ];
    }

    protected function getUserPlan(User $user): ?Plan
    {
        $sub = $user->subscription;
        if ($sub && $sub->isActive()) {
            return $sub->plan;
        }

        // Multi-tenant organization pooled subscription
        $org = $user->activeOrganization();
        if ($org) {
            $orgSub = $org->activeSubscription();
            if ($orgSub && $orgSub->isActive()) {
                return $orgSub->plan;
            }
        }

        // Return Free plan as default
        return Plan::where('slug', 'free')->first();
    }

    protected function getActiveOverride(User $user, string $featureKey): ?SubscriptionEntitlementOverride
    {
        return SubscriptionEntitlementOverride::where('user_id', $user->id)
            ->where('feature_key', $featureKey)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->latest('created_at')
            ->first();
    }

    protected function parseLimitValue(string $val): int
    {
        $v = strtolower(trim($val));
        if ($v === 'unlimited' || $v === '-1' || $v === 'inf') {
            return PHP_INT_MAX;
        }

        return is_numeric($v) ? (int) $v : 5;
    }

    protected function formatLimitDisplay(int $limit): string|int
    {
        return $limit === PHP_INT_MAX ? 'unlimited' : $limit;
    }
}
