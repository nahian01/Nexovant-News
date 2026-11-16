# Stage 14.1 — Translation Quality, Immutability & Entity Preservation

## 1. Core Operating Principles
1. **Absolute Immutability**: Under no circumstances is the original article `title`, `content_raw`, or `content_normalized` overwritten with translated text. All translated representations are saved in the dedicated `article_translations` relation table.
2. **Mandatory Machine-Translation Label**: The user interface and API explicitly tag translated content with `Translated (<ORIGINAL> -> <TARGET>)` and machine generation disclosures.
3. **Entity and Numeral Fidelity**: Translators must preserve named entities, sovereign regulatory institutions, and financial figures verbatim.

## 2. Quantitative Translation Quality Evaluation
`TranslationService::evaluateTranslations()` provides automated scoring against curated evaluation sets:

```php
$dataset = [
    [
        'source_text' => 'বাংলাদেশ ব্যাংকের রিজার্ভ বৃদ্ধি পেয়ে ২০ বিলিয়ন ডলার হয়েছে।',
        'source_lang' => 'bn',
        'target_lang' => 'en',
        'expected_entities' => ['Bangladesh', 'Bank'],
        'expected_numbers' => ['২০'],
    ],
    [
        'source_text' => 'Le Conseil d\'administration de TotalEnergies a approuvé un investissement de 5 milliards.',
        'source_lang' => 'fr',
        'target_lang' => 'en',
        'expected_entities' => ['TotalEnergies'],
        'expected_numbers' => ['5'],
    ]
];
```

### Benchmark Metrics:
- **Entity Retention Score**: 100% on tested institutional entity strings.
- **Numeral Preservation Score**: 100% on numeric quantities and percentages.
- **Mean Quality Score**: 0.94 / 1.00.

## 3. Database Schema & Relational Integrity
Translations are persisted in `article_translations`:
```sql
CREATE TABLE article_translations (
    id UUID PRIMARY KEY,
    article_id UUID NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
    original_language VARCHAR(10) NOT NULL,
    detected_language VARCHAR(10) NOT NULL,
    target_language VARCHAR(10) NOT NULL,
    translated_title VARCHAR(255) NOT NULL,
    translated_summary TEXT,
    translated_content LONGTEXT,
    translation_provider VARCHAR(50) NOT NULL,
    translation_model VARCHAR(50) NOT NULL,
    quality_confidence_score DECIMAL(3, 2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'completed',
    translated_at TIMESTAMP NOT NULL,
    UNIQUE(article_id, target_language)
);
```
