<?php

declare(strict_types=1);

namespace App\Language;

final readonly class PairResult
{
    public function __construct(
        public PhrasePair $pair,
        public TokenCount $before,
        public TokenCount $after,
        public int $deltaTokens,
        public float $deltaPercent,
        public bool $regression,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'language' => $this->pair->language,
            'original' => $this->pair->original,
            'replacement' => $this->pair->replacement,
            'intent' => $this->pair->intent,
            'category' => $this->pair->category,
            'before' => $this->before->toArray(),
            'after' => $this->after->toArray(),
            'deltaTokens' => $this->deltaTokens,
            'deltaPercent' => $this->deltaPercent,
            'regression' => $this->regression,
            'deltaEvidence' => Evidence::CALCULATED,
        ];
    }
}
