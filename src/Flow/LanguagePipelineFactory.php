<?php

declare(strict_types=1);

namespace App\Flow;

use App\Corpus\SalesCorpus;
use App\Language\BenchmarkReport;
use App\Language\BenchmarkState;
use App\Language\CostProjection;
use App\Language\LanguageAggregate;
use App\Language\LanguageBaseline;
use App\Language\PairResult;
use App\Language\PhrasePair;
use App\Language\TiktokenCounter;
use Flow\FlowFactory;
use Flow\FlowInterface;
use Flow\Ip;
use RuntimeException;

final class LanguagePipelineFactory
{
    public function __construct(
        private readonly SalesCorpus $corpus,
        private readonly TiktokenCounter $tokenizer,
        private readonly FlowFactory $flowFactory = new FlowFactory(),
    ) {}

    /**
     * @return FlowInterface<mixed>
     */
    public function create(): FlowInterface
    {
        $corpus = $this->corpus;
        $tokenizer = $this->tokenizer;

        return $this->flowFactory->create(static function () use ($corpus, $tokenizer) {
            yield static function (BenchmarkState $state) use ($corpus): BenchmarkState {
                $state->passages = $corpus->passages();
                $state->pairs = $corpus->pairsFor($state->languages);
                $state->corpusSnapshot = $corpus->snapshot();
                $state->addTrace('LOAD', sprintf(
                    '%d passages, %d pairs, languages=%s',
                    count($state->passages),
                    count($state->pairs),
                    implode(',', $state->languages),
                ));

                return $state;
            };

            yield static function (BenchmarkState $state) use ($tokenizer): BenchmarkState {
                $state->baselines = self::measureBaselines($state, $tokenizer, $state->primaryTokenizer);
                $state->addTrace('TOKENIZE_BEFORE', $state->primaryTokenizer);

                return $state;
            };

            yield static function (BenchmarkState $state): BenchmarkState {
                $state->addTrace('APPLY', sprintf('%d candidate replacements already in corpus', count($state->pairs)));

                return $state;
            };

            yield static function (BenchmarkState $state) use ($tokenizer): BenchmarkState {
                $state->pairResults = [];
                foreach ($state->pairs as $pair) {
                    $state->pairResults[] = self::measurePair($pair, $tokenizer, $state->primaryTokenizer);
                }
                $state->addTrace('TOKENIZE_AFTER', sprintf('%d pairs', count($state->pairResults)));

                return $state;
            };

            yield static function (BenchmarkState $state): BenchmarkState {
                self::compare($state);
                $state->addTrace('COMPARE', $state->selfCheckPassed ? 'self-check PASS' : 'self-check FAIL');

                return $state;
            };

            yield static function (BenchmarkState $state) use ($tokenizer): BenchmarkState {
                $state->secondaryBaselines = self::measureBaselines($state, $tokenizer, $state->secondaryTokenizer);
                $state->projections = self::projectCost($state);
                $state->addTrace('ESTIMATE_COST', $state->pricing->model);

                return $state;
            };

            yield static function (BenchmarkState $state): BenchmarkState {
                $state->addTrace('REPORT', 'built');
                $state->report = new BenchmarkReport(
                    generatedAt: (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                    phpVersion: PHP_VERSION,
                    primaryTokenizer: 'yethee/tiktoken:'.$state->primaryTokenizer,
                    secondaryTokenizer: 'yethee/tiktoken:'.$state->secondaryTokenizer,
                    languages: $state->languages,
                    pricing: $state->pricing,
                    corpus: $state->corpusSnapshot,
                    baselines: $state->baselines,
                    pairs: $state->pairResults,
                    aggregates: $state->aggregates,
                    total: $state->total ?? new LanguageAggregate('all', 0, 0, 0, 0.0, 'mixed'),
                    intentAggregates: $state->intentAggregates,
                    projections: $state->projections,
                    regressions: $state->regressions,
                    secondaryBaselines: $state->secondaryBaselines,
                    trace: $state->trace,
                    selfCheckPassed: $state->selfCheckPassed,
                );

                return $state;
            };
        });
    }

    /**
     * @param list<string> $languages
     */
    public function run(array $languages = ['en', 'fr', 'de']): BenchmarkState
    {
        $state = new BenchmarkState();
        $state->languages = $languages;

        $result = FlowCollector::run($this->create(), new Ip($state));
        if (!$result instanceof BenchmarkState) {
            throw new RuntimeException('Flow did not return BenchmarkState');
        }

        return $result;
    }

    /**
     * @return list<LanguageBaseline>
     */
    private static function measureBaselines(BenchmarkState $state, TiktokenCounter $tokenizer, string $encoding): array
    {
        $joined = [];
        foreach ($state->languages as $language) {
            $parts = [];
            foreach ($state->passages as $passage) {
                $parts[] = $passage->text($language);
            }
            $joined[$language] = implode("\n\n", $parts);
        }

        $enTokens = null;
        if (isset($joined['en'])) {
            $enTokens = $tokenizer->count($joined['en'], $encoding)->tokens;
        }

        $window = $state->pricing->comparisonWindowTokens;
        $enUnits = $enTokens !== null && $enTokens > 0 ? $window / $enTokens : null;

        $baselines = [];
        foreach ($joined as $language => $text) {
            $count = $tokenizer->count($text, $encoding);
            $tokensPerWord = $count->words === 0 ? 0.0 : $count->tokens / $count->words;
            $tokensPerChar = $count->characters === 0 ? 0.0 : $count->tokens / $count->characters;
            $premium = null;
            if ($language !== 'en' && $enTokens !== null && $enTokens > 0) {
                $premium = (($count->tokens - $enTokens) / $enTokens) * 100;
            }
            $units = $count->tokens === 0 ? 0.0 : $window / $count->tokens;
            $capacity = $enUnits !== null && $enUnits > 0 ? ($units / $enUnits) * 100 : null;

            $baselines[] = new LanguageBaseline(
                language: $language,
                words: $count->words,
                characters: $count->characters,
                bytes: $count->bytes,
                tokens: $count->tokens,
                tokensPerWord: round($tokensPerWord, 4),
                tokensPerCharacter: round($tokensPerChar, 4),
                premiumVsEn: $premium === null ? null : round($premium, 2),
                contextUnits: round($units, 2),
                capacityVsEn: $capacity === null ? ($language === 'en' ? 100.0 : null) : round($capacity, 2),
                estimatedTokensBytesDiv4: $tokenizer->estimateBytesDiv4($text),
                tokenizer: $count->tokenizer,
            );
        }

        return $baselines;
    }

    private static function measurePair(PhrasePair $pair, TiktokenCounter $tokenizer, string $encoding): PairResult
    {
        $before = $tokenizer->count($pair->original, $encoding);
        $after = $tokenizer->count($pair->replacement, $encoding);
        $delta = $after->tokens - $before->tokens;
        $percent = $before->tokens === 0 ? 0.0 : ($delta / $before->tokens) * 100;

        return new PairResult(
            pair: $pair,
            before: $before,
            after: $after,
            deltaTokens: $delta,
            deltaPercent: round($percent, 2),
            regression: $delta > 0,
        );
    }

    private static function compare(BenchmarkState $state): void
    {
        $intents = ['persuasion', 'token_opt'];
        $byLanguage = [];
        $byIntent = [];
        foreach ($state->languages as $language) {
            $byLanguage[$language] = ['before' => 0, 'after' => 0];
            foreach ($intents as $intent) {
                $byIntent[$intent][$language] = ['before' => 0, 'after' => 0];
            }
        }

        $state->selfCheckPassed = true;
        foreach ($state->pairResults as $result) {
            $expectedDelta = $result->after->tokens - $result->before->tokens;
            $expectedPercent = $result->before->tokens === 0
                ? 0.0
                : round(($expectedDelta / $result->before->tokens) * 100, 2);
            if ($result->deltaTokens !== $expectedDelta || $result->deltaPercent !== $expectedPercent) {
                $state->selfCheckPassed = false;
            }
            if ($result->regression !== ($expectedDelta > 0)) {
                $state->selfCheckPassed = false;
            }

            $lang = $result->pair->language;
            $intent = $result->pair->intent;
            $byLanguage[$lang]['before'] += $result->before->tokens;
            $byLanguage[$lang]['after'] += $result->after->tokens;
            if (!isset($byIntent[$intent][$lang])) {
                $state->selfCheckPassed = false;
                continue;
            }
            $byIntent[$intent][$lang]['before'] += $result->before->tokens;
            $byIntent[$intent][$lang]['after'] += $result->after->tokens;
        }

        $state->aggregates = self::aggregatesFromSums($byLanguage, 'mixed');
        $state->total = self::totalFromAggregates($state->aggregates, 'mixed');

        $state->intentAggregates = [];
        foreach ($intents as $intent) {
            $rows = self::aggregatesFromSums($byIntent[$intent] ?? [], $intent);
            $state->intentAggregates = [
                ...$state->intentAggregates,
                ...$rows,
                self::totalFromAggregates($rows, $intent),
            ];
        }

        $state->regressions = array_values(array_filter(
            $state->pairResults,
            static fn (PairResult $result): bool => $result->regression,
        ));
    }

    /**
     * @param array<string, array{before: int, after: int}> $byLanguage
     * @param 'persuasion'|'token_opt'|'mixed'              $intent
     *
     * @return list<LanguageAggregate>
     */
    private static function aggregatesFromSums(array $byLanguage, string $intent): array
    {
        $aggregates = [];
        foreach ($byLanguage as $language => $sum) {
            $saved = $sum['before'] - $sum['after'];
            $percent = $sum['before'] === 0 ? 0.0 : ($saved / $sum['before']) * 100;
            $aggregates[] = new LanguageAggregate(
                language: $language,
                tokensBefore: $sum['before'],
                tokensAfter: $sum['after'],
                tokensSaved: $saved,
                savingPercent: round($percent, 2),
                intent: $intent,
            );
        }

        return $aggregates;
    }

    /**
     * @param list<LanguageAggregate>          $aggregates
     * @param 'persuasion'|'token_opt'|'mixed' $intent
     */
    private static function totalFromAggregates(array $aggregates, string $intent): LanguageAggregate
    {
        $before = 0;
        $after = 0;
        foreach ($aggregates as $aggregate) {
            $before += $aggregate->tokensBefore;
            $after += $aggregate->tokensAfter;
        }
        $saved = $before - $after;

        return new LanguageAggregate(
            language: 'all',
            tokensBefore: $before,
            tokensAfter: $after,
            tokensSaved: $saved,
            savingPercent: $before === 0 ? 0.0 : round(($saved / $before) * 100, 2),
            intent: $intent,
        );
    }

    /**
     * ESTIMATED from pair-token aggregates × dated prices.
     * Projects persuasion, token_opt, and mixed so a wash does not hide the split.
     *
     * @return list<CostProjection>
     */
    private static function projectCost(BenchmarkState $state): array
    {
        $rows = [];
        $subjects = $state->intentAggregates;
        foreach ($state->aggregates as $aggregate) {
            $subjects[] = $aggregate;
        }
        if ($state->total !== null) {
            $subjects[] = $state->total;
        }

        foreach ($subjects as $aggregate) {
            foreach ($state->pricing->workloads as $requests) {
                $inputBefore = $state->pricing->inputCostUsd($aggregate->tokensBefore, $requests);
                $inputAfter = $state->pricing->inputCostUsd($aggregate->tokensAfter, $requests);
                $outputBefore = $state->pricing->outputCostUsd($aggregate->tokensBefore, $requests);
                $outputAfter = $state->pricing->outputCostUsd($aggregate->tokensAfter, $requests);
                $rows[] = new CostProjection(
                    language: $aggregate->language,
                    intent: $aggregate->intent,
                    requests: $requests,
                    tokensBefore: $aggregate->tokensBefore,
                    tokensAfter: $aggregate->tokensAfter,
                    inputBeforeUsd: $inputBefore,
                    inputAfterUsd: $inputAfter,
                    inputSavedUsd: $inputBefore - $inputAfter,
                    outputBeforeUsd: $outputBefore,
                    outputAfterUsd: $outputAfter,
                    outputSavedUsd: $outputBefore - $outputAfter,
                );
            }
        }

        return $rows;
    }
}
