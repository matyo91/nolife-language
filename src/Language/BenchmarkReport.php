<?php

declare(strict_types=1);

namespace App\Language;

final readonly class BenchmarkReport
{
    /**
     * @param list<string>              $languages
     * @param list<LanguageBaseline>    $baselines
     * @param list<PairResult>          $pairs
     * @param list<LanguageAggregate>   $aggregates       mixed-intent language rows
     * @param LanguageAggregate         $total            mixed-intent all-language total
     * @param list<LanguageAggregate>   $intentAggregates persuasion / token_opt rows (languages + all)
     * @param list<CostProjection>      $projections
     * @param list<PairResult>          $regressions
     * @param list<LanguageBaseline>    $secondaryBaselines
     * @param list<string>              $trace
     * @param array<string, mixed>      $corpus
     */
    public function __construct(
        public string $generatedAt,
        public string $phpVersion,
        public string $primaryTokenizer,
        public string $secondaryTokenizer,
        public array $languages,
        public PricingAssumptions $pricing,
        public array $corpus,
        public array $baselines,
        public array $pairs,
        public array $aggregates,
        public LanguageAggregate $total,
        public array $intentAggregates,
        public array $projections,
        public array $regressions,
        public array $secondaryBaselines,
        public array $trace,
        public bool $selfCheckPassed,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'generatedAt' => $this->generatedAt,
            'phpVersion' => $this->phpVersion,
            'primaryTokenizer' => $this->primaryTokenizer,
            'secondaryTokenizer' => $this->secondaryTokenizer,
            'languages' => $this->languages,
            'pricing' => $this->pricing->toArray(),
            'corpus' => $this->corpus,
            'baselines' => array_map(static fn (LanguageBaseline $b): array => $b->toArray(), $this->baselines),
            'pairs' => array_map(static fn (PairResult $p): array => $p->toArray(), $this->pairs),
            'aggregates' => array_map(static fn (LanguageAggregate $a): array => $a->toArray(), $this->aggregates),
            'total' => $this->total->toArray(),
            'intentAggregates' => array_map(static fn (LanguageAggregate $a): array => $a->toArray(), $this->intentAggregates),
            'projections' => array_map(static fn (CostProjection $p): array => $p->toArray(), $this->projections),
            'regressions' => array_map(static fn (PairResult $p): array => $p->toArray(), $this->regressions),
            'secondaryBaselines' => array_map(static fn (LanguageBaseline $b): array => $b->toArray(), $this->secondaryBaselines),
            'trace' => $this->trace,
            'selfCheckPassed' => $this->selfCheckPassed,
        ];
    }
}
