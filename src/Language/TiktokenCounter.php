<?php

declare(strict_types=1);

namespace App\Language;

use Yethee\Tiktoken\EncoderProvider;

/**
 * MEASURED token counts via yethee/tiktoken (pure-PHP BPE).
 *
 * This is OpenAI BPE, not Anthropic/Gemini native, not a provider invoice.
 * o200k_base ≈ GPT-4o family (same encoding Caveman evals use offline).
 * cl100k_base ≈ GPT-4 / GPT-3.5-turbo family.
 */
final class TiktokenCounter
{
    public function __construct(
        private EncoderProvider $provider = new EncoderProvider(),
    ) {}

    /**
     * @param non-empty-string $encoding
     */
    public function count(string $text, string $encoding): TokenCount
    {
        $metrics = TextMetrics::measure($text);
        $tokens = count($this->provider->get($encoding)->encode($text));

        return new TokenCount(
            tokenizer: 'yethee/tiktoken:'.$encoding,
            tokens: $tokens,
            words: $metrics['words'],
            characters: $metrics['characters'],
            bytes: $metrics['bytes'],
        );
    }

    /**
     * Heuristic leftover from nolife-tokens. ESTIMATED only — never billed truth.
     */
    public function estimateBytesDiv4(string $text): int
    {
        return (int) ceil(strlen($text) / 4);
    }
}
