# Stage 14.1 — Cross-Language Search Validation & Retrieval Hardening

## 1. Unified Search Architecture
`UnifiedSearchService` executes hybrid search combining keyword full-text matching with semantic translation overlays:
```php
$articles = Article::query()
    ->where(function ($sub) use ($searchTerms, $locale) {
        $sub->where('title', 'like', "%{$searchTerms}%")
            ->orWhere('content_raw', 'like', "%{$searchTerms}%")
            ->orWhereHas('translations', function ($tQuery) use ($searchTerms, $locale) {
                if ($locale) {
                    $tQuery->where('target_language', $locale);
                }
                $tQuery->where(function ($matchSub) use ($searchTerms) {
                    $matchSub->where('translated_title', 'like', "%{$searchTerms}%")
                             ->orWhere('translated_summary', 'like', "%{$searchTerms}%");
                });
            });
    })
    ->where('status', 'published')
    ->when($tenantId, fn($q) => $q->whereNull('organization_id')->orWhere('organization_id', $tenantId));
```

## 2. Validation Test Scenarios

### Scenario 1: Cross-Language Discovery
- Query: `"Bangladesh"`
- Matched Record: `Article` with Bengali title `বাংলাদেশ ব্যাংকের মুদ্রানীতি বিবৃতি: রেপো সুদহার ৮.৫০ শতাংশে অপরিবর্তিত`.
- Match Explanation: `Multilingual translation match ('en') with 85% source credibility.`

### Scenario 2: Target Language Filter Isolation
- Filter: `language = 'bn'`
- Result: Returns only original Bengali articles.
- Filter: `language = 'en'`
- Result: Returns native English articles AND foreign articles translated to English.

### Scenario 3: Relevance Scoring & Transparency
- Results provide:
  - Base relevance score
  - Source credibility weighting
  - Corroborator count
  - Origin language indication
