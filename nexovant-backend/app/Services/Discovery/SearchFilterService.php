<?php

declare(strict_types=1);

namespace App\Services\Discovery;

use App\Services\Discovery\DTO\SearchQueryDTO;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class SearchFilterService
{
    /**
     * Apply date range filters to an Eloquent query builder.
     */
    public function applyDateFilter(Builder $query, array $filters, string $dateColumn = 'published_at'): Builder
    {
        $preset = $filters['date_preset'] ?? $filters['date_range'] ?? null;

        if ($preset) {
            $now = Carbon::now();
            match ($preset) {
                '24h', 'today' => $query->where($dateColumn, '>=', $now->subHours(24)),
                '7d', 'week' => $query->where($dateColumn, '>=', $now->subDays(7)),
                '30d', 'month' => $query->where($dateColumn, '>=', $now->subDays(30)),
                '1y', 'year' => $query->where($dateColumn, '>=', $now->subYear()),
                default => null,
            };
        }

        if (!empty($filters['start_date'])) {
            $query->where($dateColumn, '>=', Carbon::parse($filters['start_date']));
        }

        if (!empty($filters['end_date'])) {
            $query->where($dateColumn, '<=', Carbon::parse($filters['end_date']));
        }

        return $query;
    }

    /**
     * Apply verification status filter to query.
     */
    public function applyVerificationFilter(Builder $query, array $filters, string $column = 'verification_status'): Builder
    {
        if (!empty($filters['verification_status']) && $filters['verification_status'] !== 'all') {
            $status = $filters['verification_status'];
            if ($status === 'verified') {
                $query->whereIn($column, ['verified', 'confirmed']);
            } else {
                $query->where($column, $status);
            }
        }

        return $query;
    }

    /**
     * Apply credibility filter to source-related queries.
     */
    public function applyCredibilityFilter(Builder $query, array $filters, string $relation = 'source'): Builder
    {
        if (isset($filters['min_credibility']) && is_numeric($filters['min_credibility'])) {
            $min = (float) $filters['min_credibility'];
            // Normalize to decimal 0.00-1.00 scale if given as 0-100
            $minDecimal = $min > 1.0 ? $min / 100.0 : $min;

            $query->whereHas($relation, function (Builder $sq) use ($minDecimal) {
                $sq->where('credibility_score', '>=', $minDecimal);
            });
        }

        return $query;
    }

    /**
     * Apply topic and category filters.
     */
    public function applyCategoryFilter(Builder $query, array $filters): Builder
    {
        if (!empty($filters['category']) && $filters['category'] !== 'all') {
            $category = $filters['category'];
            $query->where(function (Builder $q) use ($category) {
                $q->whereHas('categories', function (Builder $cq) use ($category) {
                    $cq->where('slug', $category)->orWhere('name', $category);
                })->orWhere('primary_category', $category);
            });
        }

        return $query;
    }

    /**
     * Apply geographic/region filter.
     */
    public function applyRegionFilter(Builder $query, array $filters): Builder
    {
        if (!empty($filters['region']) && $filters['region'] !== 'all') {
            $region = $filters['region'];
            $query->where(function (Builder $q) use ($region) {
                $q->where('country', $region)
                  ->orWhere('region', $region)
                  ->orWhereJsonContains('metadata->regions', $region);
            });
        }

        return $query;
    }

    /**
     * Apply strict multi-tenant boundary constraint on tenant-scoped models.
     */
    public function applyTenantIsolation(Builder $query, ?string $tenantId, string $tenantColumn = 'tenant_id'): Builder
    {
        if ($tenantId !== null) {
            // Authorized tenant user can see their tenant's resources OR public resources
            $query->where(function (Builder $q) use ($tenantId, $tenantColumn) {
                $q->where($tenantColumn, $tenantId)
                  ->orWhereNull($tenantColumn);
            });
        } else {
            // Unauthenticated or individual user can strictly see ONLY public resources
            $query->whereNull($tenantColumn);
        }

        return $query;
    }

    /**
     * Apply sorting options.
     */
    public function applySorting(Builder $query, string $sortBy, string $dateColumn = 'published_at'): Builder
    {
        match ($sortBy) {
            'recent' => $query->orderByDesc($dateColumn),
            'oldest' => $query->orderBy($dateColumn),
            'highest_credibility' => $query->orderByDesc('credibility_score'),
            'highest_corroboration' => $query->orderByDesc('corroboration_count'),
            default => $query->orderByDesc($dateColumn), // Default recency baseline for SQL
        };

        return $query;
    }
}
