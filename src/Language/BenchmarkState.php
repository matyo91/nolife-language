<?php

declare(strict_types=1);

namespace App\Language;

/**
 * Mutable Flow packet. Jobs receive this object handle, mutate it, and
 * return the same instance. That is not a C pointer and not a PHP `&`
 * reference — see ValueSemantics.
 */
final class BenchmarkState
{
    /** @var list<string> */
    public array $languages = ['en', 'fr', 'de'];

    public string $primaryTokenizer = 'o200k_base';

    public string $secondaryTokenizer = 'cl100k_base';

    /** @var list<PhrasePair> */
    public array $pairs = [];

    /** @var list<ParallelPassage> */
    public array $passages = [];

    /** @var array<string, mixed> */
    public array $corpusSnapshot = [];

    /** @var list<PairResult> */
    public array $pairResults = [];

    /** @var list<LanguageBaseline> */
    public array $baselines = [];

    /** @var list<LanguageBaseline> */
    public array $secondaryBaselines = [];

    /** @var list<LanguageAggregate> mixed-intent language rows */
    public array $aggregates = [];

    public ?LanguageAggregate $total = null;

    /** @var list<LanguageAggregate> persuasion / token_opt rows including language=all */
    public array $intentAggregates = [];

    /** @var list<CostProjection> */
    public array $projections = [];

    /** @var list<PairResult> */
    public array $regressions = [];

    public ?BenchmarkReport $report = null;

    public bool $selfCheckPassed = false;

    /** @var list<string> */
    public array $trace = [];

    public function __construct(
        public PricingAssumptions $pricing = new PricingAssumptions(),
    ) {}

    public function addTrace(string $stage, string $detail = ''): void
    {
        $this->trace[] = $detail === '' ? $stage : $stage.': '.$detail;
    }
}
