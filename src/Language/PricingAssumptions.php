<?php

declare(strict_types=1);

namespace App\Language;

/**
 * Provider prices are configuration, not buried arithmetic.
 *
 * PHP 8.5.4: `public readonly string $x = '…';` is a compile-time error
 * (“Readonly property … cannot have default value”). Promoted constructor
 * defaults already work and are a parameter default, not the PHP 8.6
 * property-default RFC (a documented non-goal of that RFC).
 */
final readonly class PricingAssumptions
{
    /**
     * @param list<int> $workloads
     */
    public function __construct(
        public float $inputUsdPerMillion = 0.15,
        public float $outputUsdPerMillion = 0.60,
        public int $contextWindowTokens = 128_000,
        public int $comparisonWindowTokens = 100_000,
        public string $model = 'gpt-4o-mini',
        public string $currency = 'USD',
        public string $pricedAt = '2026-08-02',
        public string $source = 'OpenAI public GPT-4o-mini list (dated snapshot, not a live quote)',
        public string $evidence = Evidence::ESTIMATED,
        public array $workloads = [1, 1_000, 100_000, 1_000_000],
    ) {}

    public function inputCostUsd(int $tokens, int $requests): float
    {
        return ($tokens / 1_000_000) * $this->inputUsdPerMillion * $requests;
    }

    public function outputCostUsd(int $tokens, int $requests): float
    {
        return ($tokens / 1_000_000) * $this->outputUsdPerMillion * $requests;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'currency' => $this->currency,
            'inputUsdPerMillion' => $this->inputUsdPerMillion,
            'outputUsdPerMillion' => $this->outputUsdPerMillion,
            'contextWindowTokens' => $this->contextWindowTokens,
            'comparisonWindowTokens' => $this->comparisonWindowTokens,
            'pricedAt' => $this->pricedAt,
            'source' => $this->source,
            'workloads' => $this->workloads,
            'evidence' => $this->evidence,
        ];
    }
}
