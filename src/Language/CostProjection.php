<?php

declare(strict_types=1);

namespace App\Language;

final readonly class CostProjection
{
    /**
     * @param 'persuasion'|'token_opt'|'mixed' $intent
     */
    public function __construct(
        public string $language,
        public string $intent,
        public int $requests,
        public int $tokensBefore,
        public int $tokensAfter,
        public float $inputBeforeUsd,
        public float $inputAfterUsd,
        public float $inputSavedUsd,
        public float $outputBeforeUsd,
        public float $outputAfterUsd,
        public float $outputSavedUsd,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'language' => $this->language,
            'intent' => $this->intent,
            'requests' => $this->requests,
            'tokensBefore' => $this->tokensBefore,
            'tokensAfter' => $this->tokensAfter,
            'inputBeforeUsd' => $this->inputBeforeUsd,
            'inputAfterUsd' => $this->inputAfterUsd,
            'inputSavedUsd' => $this->inputSavedUsd,
            'outputBeforeUsd' => $this->outputBeforeUsd,
            'outputAfterUsd' => $this->outputAfterUsd,
            'outputSavedUsd' => $this->outputSavedUsd,
            'evidence' => Evidence::ESTIMATED,
        ];
    }
}
