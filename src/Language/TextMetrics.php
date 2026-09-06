<?php

declare(strict_types=1);

namespace App\Language;

use Symfony\Component\String\UnicodeString;

/**
 * Word = a run of Unicode letters or numbers. Not whitespace-split, so
 * "$1,000" is two words (1 and 000) plus the currency sign is ignored.
 * Character count is grapheme-unaware Unicode code points via Symfony String.
 */
final class TextMetrics
{
    /**
     * @return array{words: int, characters: int, bytes: int}
     */
    public static function measure(string $text): array
    {
        return [
            'words' => self::wordCount($text),
            'characters' => (new UnicodeString($text))->length(),
            'bytes' => strlen($text),
        ];
    }

    public static function wordCount(string $text): int
    {
        if (trim($text) === '') {
            return 0;
        }

        preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches);

        return count($matches[0]);
    }
}
