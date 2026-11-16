<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Models\Article;
use App\Models\IntelligenceSignal;
use App\Models\SignalEvidence;
use App\Services\Discovery\SourceCorroborationService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class IntelligenceSignalDetectionService
{
    public function __construct(
        protected SourceCorroborationService $corroborationService
    ) {}

    /**
     * Detect or update an intelligence signal from an ingested article.
     *
     * @param Article $article
     * @param string|null $tenantId
     * @return IntelligenceSignal|null
     */
    public function detectSignalFromArticle(Article $article, ?string $tenantId = null): ?IntelligenceSignal
    {
        // 1. Analyze Source Corroboration & Exclude Syndicated Reprints
        $corroboration = $this->corroborationService->getCorroborationReport((string) $article->id, 'article');
        $independentSourcesCount = max(1, count($corroboration['independent_corroborators'] ?? []) + 1);
        $credibilityScore = (float) ($corroboration['credibility_score'] ?? ($article->source?->credibility_score ? $article->source->credibility_score * 100 : 80.0));
        $verificationStatus = in_array($article->verification_status, ['verified', 'confirmed'], true) 
            ? (string) $article->verification_status 
            : ($corroboration['verification_status'] ?? 'single_source');

        // 2. Classify Category & Severity
        $category = $this->classifyCategory($article);
        $severity = $this->calculateSeverity($independentSourcesCount, $credibilityScore, $verificationStatus, $article);

        // 3. Deduplication: Check if active signal exists within last 7 days for similar entity / topic
        $existingSignal = $this->findExistingSignal($article, $category, $tenantId);

        if ($existingSignal) {
            // Update existing signal with new corroborating evidence
            $existingSignal->last_updated_at = now();
            $existingSignal->evidence_count += 1;
            $existingSignal->independent_sources_count = max($existingSignal->independent_sources_count, $independentSourcesCount);

            if ($severity === 'critical' || ($severity === 'high' && $existingSignal->severity !== 'critical')) {
                $existingSignal->severity = $severity;
            }

            $existingSignal->save();

            // Record evidence
            $this->attachEvidence($existingSignal, $article, $corroboration);

            return $existingSignal;
        }

        // 4. Create New Signal
        $title = $this->generateSignalTitle($article, $category);
        $relatedEntities = $article->companies->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'type' => 'company'])->values()->all();
        $relatedTopics = $article->categories->pluck('name')->values()->all();
        $geographicScope = $article->source?->geographic_region ?? 'GLOBAL';

        $signal = IntelligenceSignal::create([
            'title' => $title,
            'description' => (string) ($article->content_normalized ?? $article->title),
            'signal_category' => $category,
            'severity' => $severity,
            'status' => 'detected',
            'related_entities' => $relatedEntities,
            'related_topics' => $relatedTopics,
            'geographic_scope' => $geographicScope,
            'first_observed_at' => $article->published_at ?? now(),
            'last_updated_at' => now(),
            'evidence_count' => 1,
            'independent_sources_count' => $independentSourcesCount,
            'verification_status' => $verificationStatus,
            'detection_methodology' => 'ingestion_rule_corroboration',
            'review_status' => 'pending',
            'organization_id' => $tenantId,
            'analysis_history' => [
                [
                    'timestamp' => now()->toIso8601String(),
                    'action' => 'signal_detected',
                    'reason' => "Initial detection from primary source {$article->source?->name}.",
                    'severity' => $severity,
                ]
            ],
            'metadata' => [
                'initial_article_id' => $article->id,
                'source_credibility' => $credibilityScore,
            ],
        ]);

        $this->attachEvidence($signal, $article, $corroboration);

        return $signal;
    }

    /**
     * Classify signal category based on content keywords, entities, and categories.
     */
    protected function classifyCategory(Article $article): string
    {
        $text = strtolower($article->title . ' ' . ($article->content_normalized ?? ''));

        if (Str::contains($text, ['antitrust', 'sec filing', 'regulatory', 'investigation', 'compliance', 'doj', 'ftc', 'eu chips act', 'sanctions', 'export control'])) {
            return 'regulatory_development';
        }
        if (Str::contains($text, ['foundry', 'capex', 'fab', 'expansion', 'billion', 'manufacturing capacity', 'investment'])) {
            return 'significant_development';
        }
        if (Str::contains($text, ['breakthrough', 'nanometer', 'high-na', 'quantum', 'patent', 'next-gen', 'architecture'])) {
            return 'technology_breakthrough';
        }
        if (Str::contains($text, ['shortage', 'disruption', 'bottleneck', 'supplier', 'logistics', 'embargo', 'wafer shortage'])) {
            return 'supply_chain_disruption';
        }
        if (Str::contains($text, ['acquisition', 'merger', 'board approves', 'leadership', 'ceo', 'restructuring', 'pivot'])) {
            return 'corporate_strategy_change';
        }
        if (Str::contains($text, ['pricing', 'margin', 'quarterly', 'guidance', 'market share', 'valuation'])) {
            return 'market_shift';
        }
        if (Str::contains($text, ['disputes', 'contradicts', 'denies', 'unconfirmed', 'allegations'])) {
            return 'conflicting_information';
        }

        return 'emerging_trend';
    }

    /**
     * Calculate objective severity based on verifiable evidence.
     */
    protected function calculateSeverity(int $independentSources, float $credibility, string $status, Article $article): string
    {
        $isBreaking = (bool) ($article->is_breaking ?? false);
        $normCred = $credibility <= 1.0 ? ($credibility * 100.0) : $credibility;

        if ($independentSources >= 3 && $normCred >= 90.0 && in_array($status, ['verified', 'confirmed'], true) && $isBreaking) {
            return 'critical';
        }

        if (($independentSources >= 2 && $normCred >= 80.0) || ($isBreaking && $normCred >= 70.0)) {
            return 'high';
        }

        if ($independentSources >= 1 && $normCred >= 60.0) {
            return 'medium';
        }

        return 'informational';
    }

    /**
     * Find existing active signal to prevent duplicate alerts.
     */
    protected function findExistingSignal(Article $article, string $category, ?string $tenantId): ?IntelligenceSignal
    {
        $query = IntelligenceSignal::where('signal_category', $category)
            ->where('status', '!=', 'dismissed')
            ->where('first_observed_at', '>=', now()->subDays(7));

        if ($tenantId !== null) {
            $query->where('organization_id', $tenantId);
        } else {
            $query->whereNull('organization_id');
        }

        // Compare title keywords
        $words = array_filter(explode(' ', preg_replace('/[^a-zA-Z0-9 ]/', '', strtolower($article->title))), fn($w) => strlen($w) > 4);
        
        foreach ($query->get() as $sig) {
            $sigTitle = strtolower($sig->title);
            $matchCount = 0;
            foreach ($words as $word) {
                if (str_contains($sigTitle, $word)) {
                    $matchCount++;
                }
            }
            if ($matchCount >= 2) {
                return $sig;
            }
        }

        return null;
    }

    protected function generateSignalTitle(Article $article, string $category): string
    {
        $prefix = match ($category) {
            'technology_breakthrough' => 'Tech Breakthrough:',
            'supply_chain_disruption' => 'Supply Alert:',
            'regulatory_development' => 'Regulatory Shift:',
            'corporate_strategy_change' => 'Strategic Pivot:',
            'significant_development' => 'Major Signal:',
            'conflicting_information' => 'Conflicting Intel:',
            default => 'Signal Detected:'
        };

        return "{$prefix} {$article->title}";
    }

    protected function attachEvidence(IntelligenceSignal $signal, Article $article, array $corroboration): void
    {
        SignalEvidence::create([
            'signal_id' => $signal->id,
            'article_id' => $article->id,
            'source_id' => $article->source?->id,
            'evidence_type' => 'supporting',
            'excerpt' => Str::limit((string) ($article->content_normalized ?? $article->title), 350),
            'source_name' => $article->source?->name ?? 'Wire Outlet',
            'source_url' => $article->canonical_url,
            'is_independent' => true,
            'credibility_score' => (float) ($article->source?->credibility_score ?? 85.0),
            'published_at' => $article->published_at ?? now(),
        ]);
    }
}
