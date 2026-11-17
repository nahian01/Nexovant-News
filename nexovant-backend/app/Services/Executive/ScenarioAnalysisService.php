<?php

declare(strict_types=1);

namespace App\Services\Executive;

use App\Models\IntelligenceScenario;
use App\Models\User;
use App\Services\AI\AIRouterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ScenarioAnalysisService
{
    public const VALID_TYPES = ['optimistic', 'baseline', 'adverse', 'alternative'];

    public function __construct(
        protected AIRouterService $aiRouter
    ) {}

    /**
     * Create or model an Intelligence Scenario.
     */
    public function createScenario(array $params, User $user): IntelligenceScenario
    {
        $type = $params['scenario_type'] ?? 'baseline';
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException("Invalid scenario type: {$type}");
        }

        $subject = $params['subject'] ?? 'Strategic Market Expansion';
        $title = $params['title'] ?? (ucfirst($type) . ' Scenario: ' . $subject);
        $tenantId = $user->organization_id;

        return IntelligenceScenario::create([
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::random(6),
            'organization_id' => $tenantId,
            'user_id' => $user->id,
            'subject' => $subject,
            'scenario_type' => $type,
            'description' => $params['description'] ?? "Plausible future projection modeling {$type} development paths for {$subject}.",
            'initial_conditions' => $params['initial_conditions'] ?? 'Current baseline operational environment with established regulatory guardrails.',
            'key_assumptions' => $params['key_assumptions'] ?? [
                'Capital costs remain within +/- 50bps of current rates.',
                'Supply agreements remain enforceable over the 12-month horizon.',
            ],
            'trigger_events' => $params['trigger_events'] ?? [
                'Competitor announcement of competitive next-gen product.',
                'Regulatory approval of proposed industry standards.',
            ],
            'influencing_factors' => $params['influencing_factors'] ?? [
                'Consumer purchasing power index.',
                'Cross-border trade agreements.',
            ],
            'potential_outcomes' => $params['potential_outcomes'] ?? match ($type) {
                'optimistic' => 'Accelerated adoption leading to +25% market share capture and enhanced margin retention.',
                'adverse' => 'Regulatory friction and supply disruptions leading to delayed rollout and margin compression.',
                default => 'Orderly progression meeting planned quarterly commercial milestones with manageable variance.',
            },
            'risks' => $params['risks'] ?? [
                'Underestimating competitor technological response.',
                'Execution delays in facility qualification.',
            ],
            'opportunities' => $params['opportunities'] ?? [
                'First-mover advantage in specialized regional sub-segments.',
                'Strategic enterprise partnership alliances.',
            ],
            'time_horizon' => $params['time_horizon'] ?? 'medium_term_12m',
            'supporting_evidence' => $params['supporting_evidence'] ?? [
                ['source' => 'Verified Intelligence Reports', 'excerpt' => 'Demonstrated multi-year secular growth.'],
            ],
            'contradictory_evidence' => $params['contradictory_evidence'] ?? [
                ['source' => 'Independent Industry Audit', 'excerpt' => 'Potential near-term capex pauses.'],
            ],
            'indicators_to_monitor' => $params['indicators_to_monitor'] ?? [
                'Leading wafer foundry monthly throughput data.',
                'Quarterly capital expenditure disclosures.',
            ],
            'uncertainty_rating' => $params['uncertainty_rating'] ?? 'medium',
            'is_custom' => $params['is_custom'] ?? false,
            'is_archived' => false,
        ]);
    }

    /**
     * Generate standard triad (Optimistic, Baseline, Adverse) for a subject.
     */
    public function generateScenarioSuite(string $subject, User $user): array
    {
        $types = ['optimistic', 'baseline', 'adverse'];
        $scenarios = [];

        foreach ($types as $type) {
            $scenarios[] = $this->createScenario([
                'subject' => $subject,
                'scenario_type' => $type,
                'title' => ucfirst($type) . ' Scenario: ' . $subject,
            ], $user);
        }

        return $scenarios;
    }

    /**
     * Generate comparative side-by-side matrix across scenarios for a subject.
     */
    public function compareScenarios(array $scenarioIds, User $user): array
    {
        $scenarios = IntelligenceScenario::whereIn('id', $scenarioIds)->get();

        // Verify tenant authorization for every scenario
        foreach ($scenarios as $sc) {
            if (!$user->isAdmin() && $sc->organization_id !== $user->organization_id && $sc->user_id !== $user->id) {
                abort(403, 'Unauthorized access to scenario comparison.');
            }
        }

        $comparison = [
            'scenarios' => $scenarios,
            'dimensions' => [
                'impact_comparison' => $scenarios->map(fn($s) => [
                    'scenario' => $s->title,
                    'type' => $s->scenario_type,
                    'outcome' => $s->potential_outcomes,
                ]),
                'key_assumptions' => $scenarios->map(fn($s) => [
                    'scenario' => $s->title,
                    'assumptions' => $s->key_assumptions,
                ]),
                'trigger_conditions' => $scenarios->map(fn($s) => [
                    'scenario' => $s->title,
                    'triggers' => $s->trigger_events,
                ]),
                'uncertainty_and_evidence' => $scenarios->map(fn($s) => [
                    'scenario' => $s->title,
                    'uncertainty' => $s->uncertainty_rating,
                    'evidence_strength' => count($s->supporting_evidence ?? []) >= 2 ? 'High' : 'Moderate',
                ]),
            ],
            'methodology_disclosure' => 'Scenario analysis models plausible divergence paths and initial conditions; it does not constitute guaranteed predictions or autonomous corporate advice.',
        ];

        return $comparison;
    }

    /**
     * Duplicate scenario for user customization.
     */
    public function duplicateScenario(string $id, User $user): IntelligenceScenario
    {
        $original = IntelligenceScenario::where('id', $id)->firstOrFail();
        if (!$user->isAdmin() && $original->organization_id !== $user->organization_id && $original->user_id !== $user->id) {
            abort(403, 'Unauthorized access to scenario item.');
        }

        $copy = $original->replicate([
            'id',
            'created_at',
            'updated_at',
        ]);
        $copy->title = $original->title . ' (Copy)';
        $copy->slug = Str::slug($copy->title) . '-' . Str::random(6);
        $copy->user_id = $user->id;
        $copy->is_custom = true;
        $copy->save();

        return $copy;
    }

    /**
     * Update custom scenario.
     */
    public function updateScenario(string $id, array $data, User $user): IntelligenceScenario
    {
        $scenario = IntelligenceScenario::where('id', $id)->firstOrFail();
        if (!$user->isAdmin() && $scenario->organization_id !== $user->organization_id && $scenario->user_id !== $user->id) {
            abort(403, 'Unauthorized access to scenario.');
        }

        $scenario->fill($data);
        $scenario->save();

        return $scenario;
    }

    /**
     * Archive scenario.
     */
    public function archiveScenario(string $id, User $user): IntelligenceScenario
    {
        $scenario = IntelligenceScenario::where('id', $id)->firstOrFail();
        if (!$user->isAdmin() && $scenario->organization_id !== $user->organization_id && $scenario->user_id !== $user->id) {
            abort(403, 'Unauthorized access to scenario.');
        }

        $scenario->is_archived = true;
        $scenario->save();

        return $scenario;
    }

    /**
     * Query scenarios with tenant filtering.
     */
    public function getScenarios(array $filters, User $user)
    {
        $query = IntelligenceScenario::query()->with('author');

        if (!$user->isAdmin()) {
            $tenantId = $user->organization_id;
            if ($tenantId !== null) {
                $query->where(function (Builder $q) use ($tenantId, $user) {
                    $q->where('organization_id', $tenantId)
                      ->orWhere(function ($sub) use ($user) {
                          $sub->whereNull('organization_id')->where('user_id', $user->id);
                      });
                });
            } else {
                $query->where('user_id', $user->id);
            }
        }

        if (!empty($filters['subject'])) {
            $query->where('subject', 'like', "%{$filters['subject']}%");
        }
        if (!empty($filters['scenario_type'])) {
            $query->where('scenario_type', $filters['scenario_type']);
        }
        if (isset($filters['is_archived'])) {
            $query->where('is_archived', filter_var($filters['is_archived'], FILTER_VALIDATE_BOOLEAN));
        } else {
            $query->where('is_archived', false);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }
}
