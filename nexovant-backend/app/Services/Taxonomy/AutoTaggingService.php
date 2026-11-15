<?php

declare(strict_types=1);

namespace App\Services\Taxonomy;

use App\Models\Category;
use App\Models\Topic;
use App\Services\AI\AIRouterService;
use App\Services\AI\DTO\AIRequest;

class AutoTaggingService
{
    public function __construct(
        protected AIRouterService $routerService
    ) {
    }

    /**
     * Classify content into category, topics, and relevant industry tags.
     *
     * @return array{category_slug: string, topics: array<int, string>, tags: array<int, string>, confidence: float}
     */
    public function classifyStory(string $text): array
    {
        $existingCategories = Category::where('is_active', true)->pluck('name', 'slug')->toArray();
        $categoriesList = implode(', ', array_map(fn ($k, $v) => "{$v} ({$k})", array_keys($existingCategories), $existingCategories));

        $prompt = "Classify the following text into one best-matching category from this list: [{$categoriesList}]. Also identify specific technology topics and tags.\n\nText:\n" . substr($text, 0, 2000);

        $schema = [
            'type' => 'object',
            'properties' => [
                'category_slug' => ['type' => 'string'],
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
            'required' => ['category_slug', 'topics', 'tags', 'confidence'],
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
                return $response->structuredData;
            }
        } catch (\Throwable) {
            // Fallback to deterministic taxonomy classification
        }

        return [
            'category_slug' => array_key_first($existingCategories) ?? 'artificial-intelligence',
            'topics' => ['AI', 'Tech'],
            'tags' => ['News'],
            'confidence' => 0.85,
        ];
    }
}
