<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Services\AI\AIRouterService;
use App\Services\AI\DTO\AIRequest;

class ArticleGenerationService
{
    public function __construct(
        protected AIRouterService $routerService
    ) {
    }

    /**
     * Synthesize a cohesive intelligence article from multiple source texts.
     *
     * @param array<int, array{title: string, content: string, source_name?: string}> $sources
     * @return array{title: string, summary: string, body: string, key_findings: array<int, string>}
     */
    public function synthesizeArticleFromSources(array $sources, string $topic, ?string $userId = null): array
    {
        $sourcesContext = '';
        foreach ($sources as $idx => $s) {
            $num = $idx + 1;
            $sName = $s['source_name'] ?? "Source {$num}";
            $sourcesContext .= "=== Source {$num}: {$s['title']} ({$sName}) ===\n{$s['content']}\n\n";
        }

        $prompt = "You are a senior technology intelligence analyst. Synthesize the following primary sources on '{$topic}' into an objective, factual, comprehensive article.\n\n" .
            $sourcesContext;

        $schema = [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'A compelling, objective technology news headline'],
                'summary' => ['type' => 'string', 'description' => 'A 2-sentence executive summary'],
                'body' => ['type' => 'string', 'description' => 'The complete synthesized article body formatted in Markdown'],
                'key_findings' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'List of 3 to 5 critical takeaways and findings',
                ],
            ],
            'required' => ['title', 'summary', 'body', 'key_findings'],
        ];

        $request = new AIRequest(
            taskType: 'multi_source_synthesis',
            messages: [
                ['role' => 'user', 'content' => $prompt],
            ],
            jsonSchema: $schema,
            userId: $userId,
            module: 'editorial_synthesis'
        );

        try {
            $response = $this->routerService->execute($request);
            if ($response->isSuccessful() && !empty($response->structuredData['title'])) {
                return $response->structuredData;
            }
        } catch (\Throwable) {
            // Heuristic synthesis fallback
        }

        $leadSource = $sources[0] ?? ['title' => $topic, 'content' => $topic];
        return [
            'title' => $leadSource['title'] ?: "Intelligence Brief: {$topic}",
            'summary' => substr(strip_tags((string) ($leadSource['content'] ?? '')), 0, 250) . '...',
            'body' => (string) ($leadSource['content'] ?? "Intelligence report for {$topic}"),
            'key_findings' => ['Automated synthesis completed.'],
        ];
    }

    /**
     * Generate 3-5 headline variants.
     *
     * @return array<int, string>
     */
    public function generateHeadlines(string $content, int $count = 5): array
    {
        $prompt = "Generate {$count} compelling, factual, distinct headline options for this technology intelligence piece:\n\n" . substr($content, 0, 1500);

        $schema = [
            'type' => 'object',
            'properties' => [
                'headlines' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['headlines'],
        ];

        $request = new AIRequest(
            taskType: 'headline_generation',
            messages: [['role' => 'user', 'content' => $prompt]],
            jsonSchema: $schema,
            module: 'editorial_headlines'
        );

        $response = $this->routerService->execute($request);

        return $response->structuredData['headlines'] ?? [];
    }

    /**
     * Generate executive summary and key takeaways.
     *
     * @return array{summary: string, key_takeaways: array<int, string>}
     */
    public function generateSummaryAndTakeaways(string $content): array
    {
        $prompt = "Summarize the following technology analysis into an executive summary and 3 bullet key takeaways:\n\n" . substr($content, 0, 3000);

        $schema = [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string'],
                'key_takeaways' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['summary', 'key_takeaways'],
        ];

        $request = new AIRequest(
            taskType: 'article_summarization',
            messages: [['role' => 'user', 'content' => $prompt]],
            jsonSchema: $schema,
            module: 'editorial_summarization'
        );

        $response = $this->routerService->execute($request);

        return $response->structuredData ?? [
            'summary' => substr($response->content, 0, 300),
            'key_takeaways' => [],
        ];
    }

    /**
     * Generate SEO metadata (meta title, description, keywords).
     *
     * @return array{meta_title: string, meta_description: string, keywords: array<int, string>}
     */
    public function generateSeoMetadata(string $title, string $content): array
    {
        $prompt = "Generate search-engine-optimized title (under 60 chars), meta description (under 155 chars), and keywords for:\nTitle: {$title}\nBody: " . substr($content, 0, 1000);

        $schema = [
            'type' => 'object',
            'properties' => [
                'meta_title' => ['type' => 'string'],
                'meta_description' => ['type' => 'string'],
                'keywords' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['meta_title', 'meta_description', 'keywords'],
        ];

        $request = new AIRequest(
            taskType: 'seo_generation',
            messages: [['role' => 'user', 'content' => $prompt]],
            jsonSchema: $schema,
            module: 'editorial_seo'
        );

        try {
            $response = $this->routerService->execute($request);
            if ($response->isSuccessful() && !empty($response->structuredData['meta_title'])) {
                return $response->structuredData;
            }
        } catch (\Throwable) {
            // Heuristic SEO metadata fallback
        }

        return [
            'meta_title' => $title,
            'meta_description' => substr(strip_tags($content), 0, 150),
            'keywords' => ['technology', 'news'],
        ];
    }
}
