<?php

declare(strict_types=1);

namespace App\Language;

final readonly class LanguageAggregate
{
    /**
     * @param 'persuasion'|'token_opt'|'mixed' $intent
     */
    public function __construct(
        public string $language,
        public int $tokensBefore,
        public int $tokensAfter,
        public int $tokensSaved,
        public float $savingPercent,
        public string $intent = 'mixed',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'language' => $this->language,
            'intent' => $this->intent,
            'tokensBefore' => $this->tokensBefore,
            'tokensAfter' => $this->tokensAfter,
            'tokensSaved' => $this->tokensSaved,
            'savingPercent' => $this->savingPercent,
            'evidence' => Evidence::CALCULATED,
        ];
    }
}
