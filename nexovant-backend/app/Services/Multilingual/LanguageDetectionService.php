<?php

declare(strict_types=1);

namespace App\Services\Multilingual;

class LanguageDetectionService
{
    /**
     * Detect the language of a given text.
     *
     * @return array{language: string, confidence: float, is_reliable: bool}
     */
    public function detect(string $text): array
    {
        $clean = trim($text);
        if (mb_strlen($clean) === 0) {
            return ['language' => 'en', 'confidence' => 0.5, 'is_reliable' => false];
        }

        $totalLen = mb_strlen($clean);

        // 1. Bengali Script Detection (Unicode block \x{0980}-\x{09FF})
        $bengaliMatches = preg_match_all('/[\x{0980}-\x{09FF}]/u', $clean);
        if ($bengaliMatches && ($bengaliMatches / $totalLen) > 0.10) {
            $confidence = min(0.99, 0.70 + (($bengaliMatches / $totalLen) * 0.3));
            return [
                'language' => 'bn',
                'confidence' => round($confidence, 2),
                'is_reliable' => true,
            ];
        }

        // 2. Arabic Script Detection (Unicode block \x{0600}-\x{06FF})
        $arabicMatches = preg_match_all('/[\x{0600}-\x{06FF}]/u', $clean);
        if ($arabicMatches && ($arabicMatches / $totalLen) > 0.10) {
            $confidence = min(0.99, 0.70 + (($arabicMatches / $totalLen) * 0.3));
            return [
                'language' => 'ar',
                'confidence' => round($confidence, 2),
                'is_reliable' => true,
            ];
        }

        // 3. Russian / Cyrillic Detection (\x{0400}-\x{04FF})
        $cyrillicMatches = preg_match_all('/[\x{0400}-\x{04FF}]/u', $clean);
        if ($cyrillicMatches && ($cyrillicMatches / $totalLen) > 0.15) {
            return [
                'language' => 'ru',
                'confidence' => 0.95,
                'is_reliable' => true,
            ];
        }

        // 4. Token-based analysis for Latin alphabet languages
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($clean), -1, PREG_SPLIT_NO_EMPTY);
        if (!$words || count($words) < 2) {
            return ['language' => 'en', 'confidence' => 0.6, 'is_reliable' => false];
        }

        $wordCounts = array_count_values($words);
        $totalWords = count($words);

        $frenchStopWords = ['le', 'la', 'les', 'des', 'du', 'dans', 'pour', 'avec', 'est', 'sont', 'sur', 'plus', 'par', 'ce', 'cette', 'qui', 'que', 'en', 'une', 'un', 'au', 'aux'];
        $germanStopWords = ['der', 'die', 'das', 'und', 'in', 'den', 'von', 'zu', 'dem', 'des', 'ein', 'eine', 'einer', 'ist', 'sind', 'nicht', 'mit', 'auf', 'für', 'im', 'auch'];
        $spanishStopWords = ['el', 'la', 'los', 'las', 'de', 'en', 'y', 'un', 'una', 'con', 'por', 'para', 'que', 'del', 'al', 'es', 'son', 'como', 'su', 'sus'];
        $englishStopWords = ['the', 'and', 'of', 'to', 'in', 'a', 'is', 'that', 'for', 'it', 'as', 'was', 'with', 'on', 'are', 'by', 'this', 'have', 'from', 'at'];

        $scores = [
            'fr' => $this->scoreTokens($wordCounts, $frenchStopWords),
            'de' => $this->scoreTokens($wordCounts, $germanStopWords),
            'es' => $this->scoreTokens($wordCounts, $spanishStopWords),
            'en' => $this->scoreTokens($wordCounts, $englishStopWords),
        ];

        arsort($scores);
        $topLang = array_key_first($scores);
        $topScore = $scores[$topLang];

        if ($topScore > 0) {
            $confidence = min(0.98, 0.60 + ($topScore / $totalWords));
            return [
                'language' => $topLang,
                'confidence' => round($confidence, 2),
                'is_reliable' => $confidence >= 0.70,
            ];
        }

        return ['language' => 'en', 'confidence' => 0.65, 'is_reliable' => false];
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
