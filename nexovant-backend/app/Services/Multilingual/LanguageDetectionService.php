<?php

declare(strict_types=1);

namespace App\Services\Multilingual;

use Normalizer;

class LanguageDetectionService
{
    /**
     * Detect the language of a given text.
     * Supports en, bn, ar, fr, de, es, ru with unicode normalization, script detection,
     * token frequency analysis, and uncertain/unknown classification for ambiguous inputs.
     *
     * @return array{language: string, confidence: float, is_reliable: bool}
     */
    public function detect(string $text): array
    {
        $clean = trim($text);
        if ($clean === '') {
            return ['language' => 'unknown', 'confidence' => 0.0, 'is_reliable' => false];
        }

        // Apply Unicode Normalization (NFC) if intl extension is present
        if (class_exists(Normalizer::class) && Normalizer::isNormalized($clean, Normalizer::FORM_C) === false) {
            $normalized = Normalizer::normalize($clean, Normalizer::FORM_C);
            if ($normalized !== false) {
                $clean = $normalized;
            }
        }

        $totalLen = mb_strlen($clean);

        // 1. Bengali Script Detection (Unicode block \x{0980}-\x{09FF})
        $bengaliMatches = preg_match_all('/[\x{0980}-\x{09FF}]/u', $clean);
        if ($bengaliMatches && ($bengaliMatches / $totalLen) > 0.08) {
            $confidence = min(0.99, 0.70 + (($bengaliMatches / $totalLen) * 0.3));
            return [
                'language' => 'bn',
                'confidence' => round($confidence, 2),
                'is_reliable' => true,
            ];
        }

        // 2. Arabic Script Detection (Unicode blocks \x{0600}-\x{06FF}, \x{0750}-\x{077F}, \x{08A0}-\x{08FF})
        $arabicMatches = preg_match_all('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]/u', $clean);
        if ($arabicMatches && ($arabicMatches / $totalLen) > 0.08) {
            $confidence = min(0.99, 0.70 + (($arabicMatches / $totalLen) * 0.3));
            return [
                'language' => 'ar',
                'confidence' => round($confidence, 2),
                'is_reliable' => true,
            ];
        }

        // 3. Russian / Cyrillic Detection (\x{0400}-\x{04FF})
        $cyrillicMatches = preg_match_all('/[\x{0400}-\x{04FF}]/u', $clean);
        if ($cyrillicMatches && ($cyrillicMatches / $totalLen) > 0.08) {
            return [
                'language' => 'ru',
                'confidence' => 0.95,
                'is_reliable' => true,
            ];
        }

        // 4. Token-based analysis for Latin alphabet languages
        // Strip punctuation, numbers, currencies, quotes, and whitespace
        $words = preg_split('/[^\p{L}]+/u', mb_strtolower($clean), -1, PREG_SPLIT_NO_EMPTY);
        if (!$words || count($words) === 0) {
            return ['language' => 'unknown', 'confidence' => 0.0, 'is_reliable' => false];
        }

        $wordCounts = array_count_values($words);
        $totalWords = count($words);

        $frenchStopWords = ['le', 'la', 'les', 'des', 'du', 'dans', 'pour', 'avec', 'est', 'sont', 'sur', 'plus', 'par', 'ce', 'cette', 'qui', 'que', 'en', 'une', 'un', 'au', 'aux', 'ses', 'ont', 'mais'];
        $germanStopWords = ['der', 'die', 'das', 'und', 'in', 'den', 'von', 'zu', 'dem', 'des', 'ein', 'eine', 'einer', 'ist', 'sind', 'nicht', 'mit', 'auf', 'für', 'im', 'auch', 'nach', 'als'];
        $spanishStopWords = ['el', 'la', 'los', 'las', 'de', 'en', 'y', 'un', 'una', 'con', 'por', 'para', 'que', 'del', 'al', 'es', 'son', 'como', 'su', 'sus', 'mas', 'pero', 'se'];
        $englishStopWords = ['the', 'and', 'of', 'to', 'in', 'a', 'is', 'that', 'for', 'it', 'as', 'was', 'with', 'on', 'are', 'by', 'this', 'have', 'from', 'at', 'be', 'has', 'an'];

        $scores = [
            'fr' => $this->scoreTokens($wordCounts, $frenchStopWords),
            'de' => $this->scoreTokens($wordCounts, $germanStopWords),
            'es' => $this->scoreTokens($wordCounts, $spanishStopWords),
            'en' => $this->scoreTokens($wordCounts, $englishStopWords),
        ];

        arsort($scores);
        $topLang = array_key_first($scores);
        $topScore = $scores[$topLang];

        // Ambiguous / short Latin string with no characteristic stopword matches
        if ($topScore === 0) {
            if ($totalWords <= 2) {
                return ['language' => 'unknown', 'confidence' => 0.0, 'is_reliable' => false];
            }
            return ['language' => 'unknown', 'confidence' => 0.20, 'is_reliable' => false];
        }

        $confidence = min(0.98, round(0.55 + ($topScore / $totalWords) * 0.45, 2));

        return [
            'language' => $topLang,
            'confidence' => $confidence,
            'is_reliable' => $confidence >= 0.70,
        ];
    }

    /**
     * Benchmark the detector against a labeled dataset.
     *
     * @param array<int, array{text: string, expected: string, category?: string}> $samples
     * @return array{
     *     total_samples: int,
     *     correct_count: int,
     *     incorrect_count: int,
     *     accuracy: float,
     *     per_language: array<string, array{total: int, correct: int, accuracy: float}>,
     *     ambiguous_handled: int,
     *     unsupported_handled: int
     * }
     */
    public function evaluateDataset(array $samples): array
    {
        $total = count($samples);
        $correct = 0;
        $incorrect = 0;
        $perLang = [];
        $ambiguousHandled = 0;
        $unsupportedHandled = 0;

        foreach ($samples as $sample) {
            $expected = $sample['expected'];
            $result = $this->detect($sample['text']);
            $detected = $result['language'];

            if (!isset($perLang[$expected])) {
                $perLang[$expected] = ['total' => 0, 'correct' => 0, 'accuracy' => 0.0];
            }
            $perLang[$expected]['total']++;

            if ($expected === 'unknown' || $expected === 'ambiguous') {
                if ($detected === 'unknown') {
                    $correct++;
                    $perLang[$expected]['correct']++;
                    $ambiguousHandled++;
                } else {
                    $incorrect++;
                }
            } elseif ($detected === $expected) {
                $correct++;
                $perLang[$expected]['correct']++;
            } else {
                $incorrect++;
            }
        }

        foreach ($perLang as $lang => $data) {
            $perLang[$lang]['accuracy'] = $data['total'] > 0 ? round(($data['correct'] / $data['total']) * 100, 1) : 0.0;
        }

        return [
            'total_samples' => $total,
            'correct_count' => $correct,
            'incorrect_count' => $incorrect,
            'accuracy' => $total > 0 ? round(($correct / $total) * 100, 2) : 0.0,
            'per_language' => $perLang,
            'ambiguous_handled' => $ambiguousHandled,
            'unsupported_handled' => $unsupportedHandled,
        ];
    }

    /**
     * Count matches for stopword list.
     *
     * @param array<string, int> $wordCounts
     * @param array<int, string> $stopWords
     */
    protected function scoreTokens(array $wordCounts, array $stopWords): int
    {
        $score = 0;
        foreach ($stopWords as $sw) {
            if (isset($wordCounts[$sw])) {
                $score += $wordCounts[$sw];
            }
        }
        return $score;
    }
}
