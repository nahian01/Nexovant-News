<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Services\AI\AIRouterService;
use App\Services\AI\DTO\AIRequest;

class FactExtractionService
{
    public function __construct(
        protected AIRouterService $routerService
    ) {
    }

    /**
     * Extract atomic facts, entities, numbers, and verifiable statements from content.
     *
     * @return array{facts: array<int, array{claim: string, confidence: float, entity?: string}>, entities: array<int, string>}
     */
    public function extractFacts(string $rawContent, ?string $sourceName = null): array
    {
        $prompt = "Extract verifiable factual claims and named entities from the following source" .
            ($sourceName ? " ({$sourceName})" : "") . ":\n\n" . substr($rawContent, 0, 3000);

        $schema = [
            'type' => 'object',
            'properties' => [
                'facts' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'claim' => ['type' => 'string'],
                            'confidence' => ['type' => 'number', 'minimum' => 0.0, 'maximum' => 1.0],
                            'entity' => ['type' => 'string'],
                        ],
                        'required' => ['claim', 'confidence'],
                    ],
                ],
                'entities' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['facts', 'entities'],
        ];

        $request = new AIRequest(
            taskType: 'fact_extraction',
            messages: [['role' => 'user', 'content' => $prompt]],
            jsonSchema: $schema,
            module: 'intelligence_fact_extraction'
        );

        try {
            $response = $this->routerService->execute($request);

            if ($response->isSuccessful() && !empty($response->structuredData['facts'])) {
                return $response->structuredData;
            }
        } catch (\Throwable) {
            // Fallback to deterministic heuristic extraction
        }

        return $this->heuristicFactExtraction($rawContent);
    }

    /**
     * Fallback deterministic fact extraction when AI router is unconfigured or in offline environments.
     *
     * @return array{facts: array<int, array{claim: string, confidence: float, entity?: string}>, entities: array<int, string>}
     */
    protected function heuristicFactExtraction(string $content): array
    {
        $sentences = preg_split('/(?<=[.?!])\s+/', strip_tags($content), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $facts = [];
        $entities = [];

        foreach ($sentences as $sentence) {
            $clean = trim($sentence);
            if (strlen($clean) >= 15 && strlen($clean) <= 300) {
                $entity = 'Technology Sector';
                if (preg_match('/\b(Apple|Google|Microsoft|OpenAI|Nvidia|Tesla|Amazon|Meta|DeepMind|Reuters|Bloomberg)\b/i', $clean, $m)) {
                    $entity = ucfirst(strtolower($m[1]));
                    $entities[] = $entity;
                }

                $facts[] = [
                    'claim' => $clean,
                    'confidence' => 0.90,
                    'entity' => $entity,
                ];

                if (count($facts) >= 5) {
                    break;
                }
            }
        }

        if (empty($facts) && !empty($content)) {
            $facts[] = [
                'claim' => substr(strip_tags($content), 0, 200),
                'confidence' => 0.85,
                'entity' => 'General',
            ];
        }

        return [
            'facts' => $facts,
            'entities' => array_values(array_unique($entities)),
        ];
    }

    /**
     * Perform the proprietary Nexovant 10-Dimension Assessment.
     *
     * Dimensions:
     * 1. Technical Accuracy
     * 2. Source Credibility
     * 3. Market Impact
     * 4. Innovation Significance
     * 5. Strategic Timing
     * 6. Implementation Feasibility
     * 7. Security Implications
     * 8. Regulatory Alignment
     * 9. Competitive Advantage
     * 10. Long-term Viability
     *
     * @return array<string, array{score: float, justification: string}>
     */
    public function assessTenDimensions(string $content): array
    {
        $prompt = "Evaluate the following technology development across the Nexovant 10-Dimension Framework. Score each dimension from 1.0 to 10.0 with a concise justification:\n\n" . substr($content, 0, 3000);

        $dimensionSchema = [
            'type' => 'object',
            'properties' => [
                'score' => ['type' => 'number', 'minimum' => 1.0, 'maximum' => 10.0],
                'justification' => ['type' => 'string'],
            ],
            'required' => ['score', 'justification'],
        ];

        $schema = [
            'type' => 'object',
            'properties' => [
                'technical_accuracy' => $dimensionSchema,
                'source_credibility' => $dimensionSchema,
                'market_impact' => $dimensionSchema,
                'innovation_significance' => $dimensionSchema,
                'strategic_timing' => $dimensionSchema,
                'implementation_feasibility' => $dimensionSchema,
                'security_implications' => $dimensionSchema,
                'regulatory_alignment' => $dimensionSchema,
                'competitive_advantage' => $dimensionSchema,
                'long_term_viability' => $dimensionSchema,
                'overall_intelligence_score' => ['type' => 'number', 'minimum' => 1.0, 'maximum' => 10.0],
            ],
            'required' => [
                'technical_accuracy',
                'source_credibility',
                'market_impact',
                'innovation_significance',
                'overall_intelligence_score',
            ],
        ];

        $request = new AIRequest(
            taskType: 'premium_intelligence',
            messages: [['role' => 'user', 'content' => $prompt]],
            jsonSchema: $schema,
            module: 'intelligence_assessment'
        );

        $response = $this->routerService->execute($request);

        return $response->structuredData ?? [];
    }
}
