<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\IntelligenceEntity;
use App\Models\IntelligenceEvent;
use App\Models\ResearchReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicIntelligenceController
{
    /**
     * Public Company Intelligence Profile (exposing only verified data)
     */
    public function showCompany(string $slug): JsonResponse
    {
        $entity = IntelligenceEntity::where('entity_type', 'company')
            ->where('verification_status', 'verified')
            ->where('slug', $slug)
            ->with([
                'aliases:id,entity_id,alias,alias_type',
                'outgoingRelationships' => fn($q) => $q->where('is_active', true)->where('verification_status', 'verified')->with('targetEntity:id,canonical_name,slug,entity_type'),
                'incomingRelationships' => fn($q) => $q->where('is_active', true)->where('verification_status', 'verified')->with('sourceEntity:id,canonical_name,slug,entity_type'),
                'events' => fn($q) => $q->where('verification_status', 'confirmed')->orderBy('occurred_at', 'desc')->limit(10),
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'canonical_name' => $entity->canonical_name,
                'slug' => $entity->slug,
                'legal_name' => $entity->legal_name,
                'description' => $entity->description,
                'website_url' => $entity->website_url,
                'country' => $entity->country,
                'headquarters' => $entity->headquarters,
                'industry' => $entity->industry,
                'founding_year' => $entity->founding_year,
                'verification_status' => $entity->verification_status,
                'aliases' => $entity->aliases,
                'relationships' => [
                    'outgoing' => $entity->outgoingRelationships,
                    'incoming' => $entity->incomingRelationships,
                ],
                'recent_events' => $entity->events,
            ],
        ]);
    }

    /**
     * Public Executive Profile
     */
    public function showExecutive(string $slug): JsonResponse
    {
        $entity = IntelligenceEntity::where('entity_type', 'executive')
            ->where('slug', $slug)
            ->whereIn('verification_status', ['verified', 'unverified'])
            ->with([
                'outgoingRelationships' => fn($q) => $q->where('is_active', true)->with('targetEntity:id,canonical_name,slug'),
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'full_name' => $entity->canonical_name,
                'slug' => $entity->slug,
                'biography' => $entity->description,
                'current_positions' => $entity->outgoingRelationships->whereIn('relationship_type', ['employs', 'founded_by']),
                'metadata' => $entity->metadata,
            ],
        ]);
    }

    /**
     * Public Technology Profile
     */
    public function showTechnology(string $slug): JsonResponse
    {
        $entity = IntelligenceEntity::whereIn('entity_type', ['technology', 'product'])
            ->where('slug', $slug)
            ->with([
                'incomingRelationships' => fn($q) => $q->with('sourceEntity:id,canonical_name,slug,entity_type'),
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $entity->canonical_name,
                'type' => $entity->entity_type,
                'slug' => $entity->slug,
                'description' => $entity->description,
                'developed_by' => $entity->incomingRelationships->where('relationship_type', 'develops')->pluck('sourceEntity'),
            ],
        ]);
    }

    /**
     * Public Historical Event Timeline
     */
    public function listTimeline(Request $request): JsonResponse
    {
        $query = IntelligenceEvent::whereIn('verification_status', ['confirmed', 'reported_reliable'])
            ->with('primaryEntity:id,canonical_name,slug,entity_type')
            ->orderBy('occurred_at', 'desc');

        if ($request->filled('category')) {
            $query->where('event_category', $request->query('category'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->limit($request->integer('limit', 30))->get(),
        ]);
    }

    /**
     * Public Research Reports Library (Only editorial approved and public flagged)
     */
    public function listReports(): JsonResponse
    {
        $reports = ResearchReport::where('editorial_status', 'editorial_approved')
            ->where('is_public', true)
            ->with('project:id,title,output_format')
            ->withCount('citations')
            ->latest()
            ->paginate(12);

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    /**
     * Show Public Research Report
     */
    public function showReport(string $slug): JsonResponse
    {
        $report = ResearchReport::where('editorial_status', 'editorial_approved')
            ->where('is_public', true)
            ->where('slug', $slug)
            ->with(['citations:id,report_id,citation_number,claim_text,source_name,source_url,published_at'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }
}
