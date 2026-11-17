<?php

declare(strict_types=1);

namespace App\Services\Taxonomy;

use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Topic;
use App\Services\AI\AIRouterService;
use App\Services\AI\DTO\AIRequest;
use Illuminate\Support\Str;

class AutoTaggingService
{
    public function __construct(
        protected AIRouterService $routerService
    ) {
    }

    /**
     * Classify content into industry, category, subcategory, topics, and relevant tags.
     *
     * @return array{
     *     industry: string,
     *     category_name: string,
     *     category_slug: string,
     *     subcategory_name: string,
     *     subcategory_slug: string,
     *     topics: array<int, string>,
     *     tags: array<int, string>,
     *     confidence: float
     * }
     */
    public function classifyStory(string $text): array
    {
        $existingCategories = Category::where('is_active', true)->pluck('name', 'slug')->toArray();
        $categoriesHint = implode(', ', array_map(fn ($k, $v) => "{$v} ({$k})", array_keys($existingCategories), $existingCategories));

        $prompt = "You are an executive industry taxonomist for Nexovant Executive Intelligence.\n"
            . "Analyze the following news text and determine:\n"
            . "1. Overarching Industry type (e.g. Technology, Healthcare & Life Sciences, Energy & CleanTech, Financial Services, Aerospace & Defense, Automotive & Mobility, Cybersecurity, Telecommunications, Manufacturing).\n"
            . "2. Category Name (use an existing one if applicable [{$categoriesHint}], or define a new precise category).\n"
            . "3. Category Slug (kebab-case).\n"
            . "4. Subcategory Name: The specific subcategory or domain under that Category (e.g. under 'Artificial Intelligence' -> 'Generative AI & LLMs', 'AI Accelerators', 'Autonomous Agents'; under 'Semiconductors' -> 'EUV Lithography', 'Foundry Capacity', 'Advanced Packaging'; under 'Financial Services' -> 'Digital Assets', 'Capital Markets', 'Fintech Infrastructure').\n"
            . "5. Subcategory Slug (kebab-case).\n"
            . "6. Topics: Array of 3-5 granular business/tech topics.\n"
            . "7. Tags: Array of 3-6 keywords.\n"
            . "8. Confidence score between 0.0 and 1.0.\n\n"
            . "Text to analyze:\n" . substr($text, 0, 2500);

        $schema = [
            'type' => 'object',
            'properties' => [
                'industry' => ['type' => 'string'],
                'category_name' => ['type' => 'string'],
                'category_slug' => ['type' => 'string'],
                'subcategory_name' => ['type' => 'string'],
                'subcategory_slug' => ['type' => 'string'],
                'topics' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'confidence' => ['type' => 'number', 'minimum' => 0.0, 'maximum' => 1.0],
            ],
            'required' => [
                'industry',
                'category_name',
                'category_slug',
                'subcategory_name',
                'subcategory_slug',
                'topics',
                'tags',
                'confidence',
            ],
        ];

        $request = new AIRequest(
            taskType: 'story_classification',
            messages: [['role' => 'user', 'content' => $prompt]],
            jsonSchema: $schema,
            module: 'taxonomy_classification'
        );

        try {
            $response = $this->routerService->execute($request);
            if ($response->isSuccessful() && !empty($response->structuredData['category_slug'])) {
                $data = $response->structuredData;
                return [
                    'industry' => $data['industry'] ?? 'Technology',
                    'category_name' => $data['category_name'] ?? ucwords(str_replace('-', ' ', $data['category_slug'])),
                    'category_slug' => Str::slug($data['category_slug']),
                    'subcategory_name' => $data['subcategory_name'] ?? 'General',
                    'subcategory_slug' => Str::slug($data['subcategory_slug'] ?? 'general'),
                    'topics' => array_values(array_filter($data['topics'] ?? [])),
                    'tags' => array_values(array_filter($data['tags'] ?? [])),
                    'confidence' => (float) ($data['confidence'] ?? 0.88),
                ];
            }
        } catch (\Throwable) {
            // Fallback to deterministic taxonomy classification
        }

        return $this->deterministicClassify($text);
    }

    /**
     * Deterministic rule-based taxonomy classification for fallback.
     *
     * @return array{
     *     industry: string,
     *     category_name: string,
     *     category_slug: string,
     *     subcategory_name: string,
     *     subcategory_slug: string,
     *     topics: array<int, string>,
     *     tags: array<int, string>,
     *     confidence: float
     * }
     */
    protected function deterministicClassify(string $text): array
    {
        $lower = strtolower($text);

        // 1. Semiconductors & Microelectronics
        if (Str::contains($lower, ['semiconductor', 'foundry', 'lithography', 'asml', 'tsmc', 'wafer', 'euv', 'fab ', 'fabs', 'transistor', 'packaging', 'chipset', 'silicon'])) {
            return [
                'industry' => 'Technology',
                'category_name' => 'Semiconductors',
                'category_slug' => 'semiconductors',
                'subcategory_name' => 'Foundry & Lithography',
                'subcategory_slug' => 'foundry-lithography',
                'topics' => ['Semiconductors', 'Lithography', 'Foundry Capacity', 'Chip Manufacturing'],
                'tags' => ['Hardware', 'Foundry', 'Supply Chain', 'Chips'],
                'confidence' => 0.92,
            ];
        }

        // 2. Artificial Intelligence & Machine Learning
        if (Str::contains($lower, ['artificial intelligence', 'machine learning', 'llm', 'gpt', 'anthropic', 'openai', 'deepseek', 'neural network', 'inference', 'model training', 'generative ai', 'gemini'])) {
            return [
                'industry' => 'Technology',
                'category_name' => 'Artificial Intelligence',
                'category_slug' => 'artificial-intelligence',
                'subcategory_name' => 'Generative AI & LLMs',
                'subcategory_slug' => 'generative-ai-llms',
                'topics' => ['Artificial Intelligence', 'LLM Infrastructure', 'Model Training', 'Autonomous Agents'],
                'tags' => ['AI', 'Generative AI', 'Deep Learning', 'Software'],
                'confidence' => 0.94,
            ];
        }

        // 3. Energy, CleanTech & Sustainability
        if (Str::contains($lower, ['renewable energy', 'solar', 'wind turbine', 'battery', 'nuclear', 'fusion', 'smr', 'clean energy', 'cleantech', 'power grid', 'carbon capture', 'lithium'])) {
            return [
                'industry' => 'Energy & CleanTech',
                'category_name' => 'Energy & Sustainability',
                'category_slug' => 'energy-sustainability',
                'subcategory_name' => 'Clean Tech & Grid Infrastructure',
                'subcategory_slug' => 'clean-tech-grid-infrastructure',
                'topics' => ['CleanTech', 'Energy Transition', 'Battery Storage', 'Grid Modernization'],
                'tags' => ['Energy', 'Sustainability', 'CleanTech', 'Batteries'],
                'confidence' => 0.90,
            ];
        }

        // 4. Healthcare, Pharma & Life Sciences
        if (Str::contains($lower, ['biotech', 'pharma', 'clinical trial', 'drug', 'fda', 'oncology', 'vaccine', 'genomic', 'crispr', 'mrna', 'therapeutics', 'medical device'])) {
            return [
                'industry' => 'Healthcare & Life Sciences',
                'category_name' => 'Biotechnology & Healthcare',
                'category_slug' => 'biotechnology-healthcare',
                'subcategory_name' => 'Therapeutics & Clinical Pharma',
                'subcategory_slug' => 'therapeutics-clinical-pharma',
                'topics' => ['Biotechnology', 'Clinical Development', 'Pharmaceuticals', 'Healthcare Innovation'],
                'tags' => ['Healthcare', 'Biotech', 'Pharma', 'Life Sciences'],
                'confidence' => 0.91,
            ];
        }

        // 5. Financial Services, Banking & Fintech
        if (Str::contains($lower, ['fintech', 'banking', 'sec ', 'interest rate', 'federal reserve', 'treasury', 'hedge fund', 'capital market', 'crypto', 'blockchain', 'liquidity', 'valuation'])) {
            return [
                'industry' => 'Financial Services',
                'category_name' => 'Financial Services & Fintech',
                'category_slug' => 'financial-services-fintech',
                'subcategory_name' => 'Capital Markets & Fintech',
                'subcategory_slug' => 'capital-markets-fintech',
                'topics' => ['Fintech', 'Capital Markets', 'Banking', 'Regulatory Compliance'],
                'tags' => ['Finance', 'Banking', 'Fintech', 'Investment'],
                'confidence' => 0.89,
            ];
        }

        // 6. Aerospace, Defense & Space
        if (Str::contains($lower, ['aerospace', 'defense', 'pentagon', 'dod', 'satellite', 'missile', 'avionics', 'space exploration', 'rocket', 'drone', 'lockheed', 'spacex'])) {
            return [
                'industry' => 'Aerospace & Defense',
                'category_name' => 'Aerospace & Defense',
                'category_slug' => 'aerospace-defense',
                'subcategory_name' => 'Space Systems & Defense Tech',
                'subcategory_slug' => 'space-systems-defense-tech',
                'topics' => ['Aerospace', 'Defense Tech', 'Space Infrastructure', 'Autonomous Systems'],
                'tags' => ['Defense', 'Aerospace', 'Space', 'Security'],
                'confidence' => 0.91,
            ];
        }

        // 7. Automotive & Mobility
        if (Str::contains($lower, ['electric vehicle', 'ev ', 'autonomous vehicle', 'self-driving', 'automotive', 'mobility', 'lidar', 'tesla', 'waymo', 'charging network'])) {
            return [
                'industry' => 'Automotive & Mobility',
                'category_name' => 'Automotive & Mobility',
                'category_slug' => 'automotive-mobility',
                'subcategory_name' => 'Autonomous Driving & EVs',
                'subcategory_slug' => 'autonomous-driving-evs',
                'topics' => ['Electric Vehicles', 'Autonomous Driving', 'Mobility Tech', 'Automotive Supply Chain'],
                'tags' => ['Automotive', 'EV', 'Mobility', 'Autonomous'],
                'confidence' => 0.90,
            ];
        }

        // 8. Cybersecurity
        if (Str::contains($lower, ['cybersecurity', 'malware', 'ransomware', 'data breach', 'zero-day', 'cisa', 'vulnerability', 'encryption', 'threat actor', 'infosec'])) {
            return [
                'industry' => 'Technology',
                'category_name' => 'Cybersecurity',
                'category_slug' => 'cybersecurity',
                'subcategory_name' => 'Threat Intelligence & Defense',
                'subcategory_slug' => 'threat-intelligence-defense',
                'topics' => ['Cybersecurity', 'Threat Intelligence', 'Zero Trust', 'Vulnerability Research'],
                'tags' => ['Security', 'Cybersecurity', 'Threat Intel', 'Infosec'],
                'confidence' => 0.93,
            ];
        }

        // Default General Technology
        return [
            'industry' => 'Technology',
            'category_name' => 'Strategic Technology',
            'category_slug' => 'strategic-technology',
            'subcategory_name' => 'Enterprise Innovation',
            'subcategory_slug' => 'enterprise-innovation',
            'topics' => ['Enterprise Technology', 'Cloud Computing', 'Digital Transformation'],
            'tags' => ['Tech', 'Enterprise', 'Innovation'],
            'confidence' => 0.85,
        ];
    }
}
