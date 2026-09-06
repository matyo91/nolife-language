<?php

declare(strict_types=1);

namespace App\Language;

final readonly class LanguageBaseline
{
    public function __construct(
        public string $language,
        public int $words,
        public int $characters,
        public int $bytes,
        public int $tokens,
        public float $tokensPerWord,
        public float $tokensPerCharacter,
        public ?float $premiumVsEn,
        public float $contextUnits,
        public ?float $capacityVsEn,
        public int $estimatedTokensBytesDiv4,
        public string $tokenizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'language' => $this->language,
            'words' => $this->words,
            'characters' => $this->characters,
            'bytes' => $this->bytes,
            'tokens' => $this->tokens,
            'tokensPerWord' => $this->tokensPerWord,
            'tokensPerCharacter' => $this->tokensPerCharacter,
            'premiumVsEn' => $this->premiumVsEn,
            'contextUnits' => $this->contextUnits,
            'capacityVsEn' => $this->capacityVsEn,
            'estimatedTokensBytesDiv4' => $this->estimatedTokensBytesDiv4,
            'tokenizer' => $this->tokenizer,
            'countsEvidence' => Evidence::MEASURED,
            'ratiosEvidence' => Evidence::CALCULATED,
            'heuristicEvidence' => Evidence::ESTIMATED,
        ];
    }
}
