<?php

declare(strict_types=1);

namespace App\Services\Executive;

use App\Models\IntelligenceDecision;
use App\Models\IntelligenceDecisionCriterion;
use App\Models\IntelligenceDecisionMilestone;
use App\Models\IntelligenceDecisionOption;
use App\Models\IntelligenceDecisionReview;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DecisionSupportService
{
    public const DEFAULT_CRITERIA = [
        ['name' => 'strategic_alignment', 'weight' => 1.0, 'description' => 'Alignment with long-term core corporate strategy.'],
        ['name' => 'expected_benefit', 'weight' => 1.0, 'description' => 'Anticipated revenue, margin, or strategic moat enhancement.'],
        ['name' => 'cost_or_resource', 'weight' => 0.8, 'description' => 'Upfront capital and personnel resource requirements.'],
        ['name' => 'implementation_complexity', 'weight' => 0.7, 'description' => 'Technical and organizational friction during rollout.'],
        ['name' => 'operational_risk', 'weight' => 0.9, 'description' => 'Downside exposure to core business continuity.'],
        ['name' => 'time_to_impact', 'weight' => 0.6, 'description' => 'Expected velocity until measurable ROI realization.'],
        ['name' => 'reversibility', 'weight' => 0.7, 'description' => 'Ease of unwinding decision if baseline assumptions fail.'],
        ['name' => 'evidence_strength', 'weight' => 1.0, 'description' => 'Grounded empirical verification of underlying thesis.'],
    ];

    /**
     * Create a structured Decision Workflow.
     */
    public function createDecision(array $params, User $user): IntelligenceDecision
    {
        $title = $params['title'] ?? 'Strategic Capital Allocation Evaluation';
        $businessQuestion = $params['business_question'] ?? 'Should the organization commit capital to regional expansion in EMEA?';
        $tenantId = $user->organization_id;
        $deadline = isset($params['deadline']) ? Carbon::parse($params['deadline']) : now()->addDays(30);

        return DB::transaction(function () use ($title, $businessQuestion, $deadline, $params, $tenantId, $user) {
            $decision = IntelligenceDecision::create([
                'title' => $title,
                'slug' => Str::slug($title) . '-' . Str::random(6),
                'organization_id' => $tenantId,
                'user_id' => $user->id,
                'business_question' => $businessQuestion,
                'decision_owner_id' => $params['decision_owner_id'] ?? $user->id,
                'deadline' => $deadline,
                'context_and_background' => $params['context_and_background'] ?? 'Evaluating strategic trade-offs following verified supply and demand developments.',
                'status' => 'draft',
                'approval_status' => 'pending_approval',
                'metadata' => [
                    'source_intelligence_count' => count($params['options'] ?? []),
                    'is_ai_assisted' => true,
                    'ai_automation_restriction' => 'Strict human approval enforced; AI cannot bind business decisions.',
                ],
            ]);

            // Add Options
            $options = $params['options'] ?? [
                [
                    'option_name' => 'Option A: Direct Greenfield Expansion',
                    'description' => 'Deploy fully owned regional infrastructure.',
                    'advantages' => ['Full intellectual property control', 'Direct customer relationship'],
                    'disadvantages' => ['Substantial upfront capital requirement', 'Longer ramp-up timeline'],
                    'risks' => ['Regulatory permitting delays'],
                    'assumptions' => ['Sustained local demand over 36 months'],
                    'expected_outcomes' => 'Target 18% ROI by Year 3.',
                ],
                [
                    'option_name' => 'Option B: Strategic Joint Venture',
                    'description' => 'Partner with verified regional market incumbent.',
                    'advantages' => ['Lower capital risk', 'Accelerated go-to-market speed'],
                    'disadvantages' => ['Shared governance and margin split'],
                    'risks' => ['Partner alignment divergence'],
                    'assumptions' => ['Partner operational reliability remains high'],
                    'expected_outcomes' => 'Target 14% ROI by Year 2.',
                ],
            ];

            foreach ($options as $opt) {
                IntelligenceDecisionOption::create([
                    'decision_id' => $decision->id,
                    'option_name' => $opt['option_name'],
                    'description' => $opt['description'] ?? null,
                    'advantages' => $opt['advantages'] ?? [],
                    'disadvantages' => $opt['disadvantages'] ?? [],
                    'risks' => $opt['risks'] ?? [],
                    'assumptions' => $opt['assumptions'] ?? [],
                    'evidence_references' => $opt['evidence_references'] ?? [],
                    'expected_outcomes' => $opt['expected_outcomes'] ?? null,
                    'score' => $opt['score'] ?? null,
                    'is_selected' => false,
                ]);
            }

            // Add Evaluation Criteria
            $criteriaList = $params['criteria'] ?? self::DEFAULT_CRITERIA;
            foreach ($criteriaList as $crit) {
                IntelligenceDecisionCriterion::create([
                    'decision_id' => $decision->id,
                    'criterion_name' => $crit['name'],
                    'weight' => $crit['weight'] ?? 1.0,
                    'description' => $crit['description'] ?? null,
                    'scoring_methodology' => 'linear_1_to_10',
                ]);
            }

            // Create initial evaluation audit review
            IntelligenceDecisionReview::create([
                'decision_id' => $decision->id,
                'reviewer_id' => $user->id,
                'review_type' => 'pre_decision_evaluation',
                'notes' => 'Decision matrix created and options initialized.',
                'action_taken' => 'decision_created',
            ]);

            return $decision->load(['options', 'criteria', 'reviews', 'milestones']);
        });
    }

    /**
     * Add Option to an existing decision.
     */
    public function addOption(string $decisionId, array $data, User $user): IntelligenceDecisionOption
    {
        $decision = $this->getDecisionOrFail($decisionId, $user);

        return IntelligenceDecisionOption::create([
            'decision_id' => $decision->id,
            'option_name' => $data['option_name'],
            'description' => $data['description'] ?? null,
            'advantages' => $data['advantages'] ?? [],
            'disadvantages' => $data['disadvantages'] ?? [],
            'risks' => $data['risks'] ?? [],
            'assumptions' => $data['assumptions'] ?? [],
            'evidence_references' => $data['evidence_references'] ?? [],
            'expected_outcomes' => $data['expected_outcomes'] ?? null,
            'score' => $data['score'] ?? null,
            'is_selected' => false,
        ]);
    }

    /**
     * Add Milestone to a decision.
     */
    public function addMilestone(string $decisionId, array $data, User $user): IntelligenceDecisionMilestone
    {
        $decision = $this->getDecisionOrFail($decisionId, $user);

        return IntelligenceDecisionMilestone::create([
            'decision_id' => $decision->id,
            'title' => $data['title'],
            'target_date' => isset($data['target_date']) ? Carbon::parse($data['target_date']) : null,
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Update Milestone status.
     */
    public function updateMilestone(string $milestoneId, string $status, ?string $notes, User $user): IntelligenceDecisionMilestone
    {
        $milestone = IntelligenceDecisionMilestone::where('id', $milestoneId)->firstOrFail();
        $this->getDecisionOrFail($milestone->decision_id, $user);

        $milestone->status = $status;
        if ($status === 'completed') {
            $milestone->completed_at = now();
        }
        if ($notes !== null) {
            $milestone->notes = $notes;
        }
        $milestone->save();

        return $milestone;
    }

    /**
     * Record Human Decision & Approval.
     * Enforces that a verified human user authorizes the decision.
     */
    public function recordDecisionApproval(
        string $decisionId,
        string $selectedOptionId,
        string $recordedDecisionText,
        string $approvalAction, // approved, rejected
        ?string $nextSteps,
        User $user
    ): IntelligenceDecision {
        $decision = $this->getDecisionOrFail($decisionId, $user);

        if (!in_array($approvalAction, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException("Invalid approval action: {$approvalAction}");
        }

        return DB::transaction(function () use (
            $decision,
            $selectedOptionId,
            $recordedDecisionText,
            $approvalAction,
            $nextSteps,
            $user
        ) {
            // Update Options
            IntelligenceDecisionOption::where('decision_id', $decision->id)->update(['is_selected' => false]);
            $selectedOption = IntelligenceDecisionOption::where('decision_id', $decision->id)
                ->where('id', $selectedOptionId)
                ->firstOrFail();
            $selectedOption->is_selected = true;
            $selectedOption->save();

            // Update Decision
            $decision->approval_status = $approvalAction;
            $decision->status = $approvalAction === 'approved' ? 'decision_recorded' : 'draft';
            $decision->approved_by = $user->id;
            $decision->approved_at = now();
            $decision->recorded_decision = $recordedDecisionText;
            $decision->decision_date = now();
            $decision->review_date = now()->addDays(90);
            $decision->next_steps = $nextSteps;
            $decision->save();

            // Add Audit Review
            IntelligenceDecisionReview::create([
                'decision_id' => $decision->id,
                'reviewer_id' => $user->id,
                'review_type' => 'approval',
                'notes' => "Human Decision recorded by {$user->name}. Action: {$approvalAction}. Selected: {$selectedOption->option_name}.",
                'action_taken' => "decision_{$approvalAction}",
            ]);

            return $decision->load(['options', 'criteria', 'reviews', 'milestones', 'approver', 'decisionOwner']);
        });
    }

    /**
     * Record Post-Decision Review (Outcome & Assumption Tracking).
     */
    public function recordPostDecisionReview(
        string $decisionId,
        string $notes,
        ?array $changedAssumptions,
        ?string $observedResultsVsExpected,
        string $actionTaken,
        User $user
    ): IntelligenceDecisionReview {
        $decision = $this->getDecisionOrFail($decisionId, $user);

        return DB::transaction(function () use (
            $decision,
            $notes,
            $changedAssumptions,
            $observedResultsVsExpected,
            $actionTaken,
            $user
        ) {
            $review = IntelligenceDecisionReview::create([
                'decision_id' => $decision->id,
                'reviewer_id' => $user->id,
                'review_type' => 'post_decision_review',
                'notes' => $notes,
                'changed_assumptions' => $changedAssumptions,
                'observed_results_vs_expected' => $observedResultsVsExpected,
                'action_taken' => $actionTaken,
            ]);

            $decision->status = 'post_review';
            $decision->save();

            return $review;
        });
    }

    /**
     * Query decisions with multi-tenant filtering.
     */
    public function getDecisions(array $filters, User $user)
    {
        $query = IntelligenceDecision::query()->with(['author', 'decisionOwner', 'approver', 'options']);

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

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['approval_status'])) {
            $query->where('approval_status', $filters['approval_status']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get single decision with full hierarchy.
     */
    public function getDecisionOrFail(string $id, User $user): IntelligenceDecision
    {
        $query = IntelligenceDecision::with(['author', 'decisionOwner', 'approver', 'options', 'criteria', 'reviews.reviewer', 'milestones'])
            ->where('id', $id);

        if (!$user->isAdmin()) {
            $tenantId = $user->organization_id;
            if ($tenantId !== null) {
                $query->where(function (Builder $q) use ($tenantId, $user) {
                    $q->where('organization_id', $tenantId)
                      ->orWhere('user_id', $user->id);
                });
            } else {
                $query->where('user_id', $user->id);
            }
        }

        return $query->firstOrFail();
    }
}
