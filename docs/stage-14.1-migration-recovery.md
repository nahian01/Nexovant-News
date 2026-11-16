# Stage 14.1 — Migration Recovery & Production Rollback Procedure

## 1. Migration Overview
Stage 14 introduced database schema migration:
`nexovant-backend/database/migrations/0001_01_01_000108_create_stage14_source_network_tables.php`

### Created Tables:
1. `source_health_logs`
2. `source_diagnostic_incidents`
3. `source_onboarding_reviews`
4. `source_editorial_overrides`
5. `source_reliability_snapshots`
6. `source_corroboration_records`
7. `article_translations`
8. `coverage_gap_alerts`

## 2. Safe Rollback Procedure (Expand-Contract Compliance)
Per Production Rule 7.4 (*Rollback and data safety*):
> "Never execute blind `migrate:rollback --step=1` in production if dependent jobs or active foreign keys exist."

### Pre-Rollback Pre-flight Checks:
1. Verify no pending background jobs in `jobs` table targeting `ProcessIngestedArticleIntelligenceJob`.
   ```bash
   php artisan queue:check
   ```
2. Verify existing articles have not created cascade-locking foreign keys on `article_translations`.

### Reversible Migration `down()` Method:
```php
public function down(): void
{
    Schema::dropIfExists('coverage_gap_alerts');
    Schema::dropIfExists('article_translations');
    Schema::dropIfExists('source_corroboration_records');
    Schema::dropIfExists('source_reliability_snapshots');
    Schema::dropIfExists('source_editorial_overrides');
    Schema::dropIfExists('source_onboarding_reviews');
    Schema::dropIfExists('source_diagnostic_incidents');
    Schema::dropIfExists('source_health_logs');

    if (Schema::hasTable('news_sources')) {
        Schema::table('news_sources', function (Blueprint $table) {
            $table->dropColumn([
                'source_type', 'editorial_status', 'auth_type', 'encrypted_credentials',
                'continent', 'language', 'sampling_rate_minutes', 'etag',
                'last_modified_header', 'total_items_ingested', 'consecutive_failures',
                'historical_accuracy_score', 'primary_source_ratio', 'transparency_score',
                'peer_corroboration_rate', 'reliability_score', 'calculated_tier',
                'rate_limit_per_minute', 'attribution_requirements'
            ]);
        });
    }
}
```

### Rollback Execution Command:
```bash
php artisan migrate:rollback --path=database/migrations/0001_01_01_000108_create_stage14_source_network_tables.php
```
After rollback, verify database integrity and execute `php artisan config:clear`.
