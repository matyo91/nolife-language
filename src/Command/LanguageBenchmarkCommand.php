<?php

declare(strict_types=1);

namespace App\Command;

use App\Flow\LanguagePipelineFactory;
use App\Language\BenchmarkReport;
use App\Language\BenchmarkState;
use App\Language\CostProjection;
use App\Language\LanguageAggregate;
use App\Language\LanguageBaseline;
use App\Language\PairResult;
use App\Language\ValueSemantics;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:language:benchmark',
    description: 'Measure EN/FR/DE sales-language token cost with a real tokenizer',
)]
final class LanguageBenchmarkCommand extends Command
{
    private const LANGUAGES = ['en', 'fr', 'de'];

    public function __construct(
        private readonly LanguagePipelineFactory $pipelineFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'language',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Language to include (en, fr, de). Repeatable. Default: all three.',
            )
            ->addOption(
                'format',
                null,
                InputOption::VALUE_REQUIRED,
                'table or json',
                'table',
            )
            ->addOption(
                'explain-values',
                null,
                InputOption::VALUE_NONE,
                'Print the PHP value / reference / object-handle demonstration',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $languages = $this->resolveLanguages($input->getOption('language'));
        if ($languages === []) {
            $io->error('language must be en, fr, and/or de');

            return Command::FAILURE;
        }

        $format = (string) $input->getOption('format');
        if (!in_array($format, ['table', 'json'], true)) {
            $io->error('format must be table or json');

            return Command::FAILURE;
        }

        $state = $this->pipelineFactory->run($languages);
        if ($state->report === null) {
            $io->error('Pipeline did not produce a report. Flow may not have run.');

            return Command::FAILURE;
        }

        $path = $this->writeArtifact($state->report);

        if ($format === 'json') {
            $output->writeln(json_encode($state->report->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->renderTables($io, $state->report);
            $io->note(sprintf('JSON artifact: %s (also var/benchmark/latest.json)', $path));
        }

        if ($input->getOption('explain-values')) {
            $this->renderValueSemantics($io, $state);
        }

        if (!$state->selfCheckPassed) {
            $io->error('Self-check failed: a calculated delta does not match raw token counts.');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @param mixed $raw
     *
     * @return list<string>
     */
    private function resolveLanguages(mixed $raw): array
    {
        if (!is_array($raw) || $raw === []) {
            return self::LANGUAGES;
        }

        $languages = [];
        foreach ($raw as $value) {
            if (!is_string($value) || !in_array($value, self::LANGUAGES, true)) {
                return [];
            }
            $languages[] = $value;
        }

        return array_values(array_unique($languages));
    }

    private function writeArtifact(BenchmarkReport $report): string
    {
        $dir = dirname(__DIR__, 2).'/var/benchmark';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create '.$dir);
        }

        $json = json_encode($report->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";
        $stamp = (new \DateTimeImmutable())->format('Ymd-His');
        $path = $dir.'/'.$stamp.'.json';
        file_put_contents($path, $json);
        file_put_contents($dir.'/latest.json', $json);

        return $path;
    }

    private function renderTables(SymfonyStyle $io, BenchmarkReport $report): void
    {
        $io->title('Nolife Language');
        $io->text([
            sprintf('PHP %s  |  primary %s  |  secondary %s', $report->phpVersion, $report->primaryTokenizer, $report->secondaryTokenizer),
            sprintf('Corpus %s  hash %s', $report->corpus['version'] ?? '?', substr((string) ($report->corpus['hash'] ?? ''), 0, 12)),
            sprintf('Pricing %s @ %s/%s per 1M in/out (%s, %s)', $report->pricing->model, $report->pricing->inputUsdPerMillion, $report->pricing->outputUsdPerMillion, $report->pricing->pricedAt, $report->pricing->evidence),
        ]);

        $io->section('1. Same idea, three languages');
        $io->text(self::baselineCaption($report));
        $primaryLabel = self::encodingLabel($report->primaryTokenizer);
        $secondaryLabel = self::encodingLabel($report->secondaryTokenizer);
        $secondaryByLanguage = self::indexByLanguage($report->secondaryBaselines);
        $io->table(
            ['Language', $primaryLabel, 'vs EN', $secondaryLabel, 'vs EN', 'Words', 'Chars', 'Heuristic bytes/4'],
            array_map(static function (LanguageBaseline $b) use ($secondaryByLanguage): array {
                $secondary = $secondaryByLanguage[$b->language] ?? null;

                return [
                    self::languageName($b->language),
                    (string) $b->tokens,
                    self::premiumCell($b->premiumVsEn),
                    $secondary === null ? 'n/a' : (string) $secondary->tokens,
                    $secondary === null ? 'n/a' : self::premiumCell($secondary->premiumVsEn),
                    (string) $b->words,
                    (string) $b->characters,
                    sprintf('%d ESTIMATED', $b->estimatedTokensBytesDiv4),
                ];
            }, $report->baselines),
        );

        $io->section('2. Sales substitutions (MEASURED before/after, CALCULATED delta)');
        $io->table(
            ['Lang', 'Intent', 'Original', 'Replacement', 'Before', 'After', 'Delta', 'Delta %'],
            array_map(static function (PairResult $r): array {
                return [
                    $r->pair->language,
                    $r->pair->intent,
                    $r->pair->original,
                    $r->pair->replacement,
                    (string) $r->before->tokens,
                    (string) $r->after->tokens,
                    (string) $r->deltaTokens,
                    sprintf('%+.2f%%', $r->deltaPercent),
                ];
            }, $report->pairs),
        );

        $io->section('3. Aggregate by intent (CALCULATED from pair tokens)');
        $io->text('Persuasion and token_opt are separate totals. Mixed alone can hide opposing movements.');
        foreach (['persuasion', 'token_opt'] as $intent) {
            $io->text(sprintf('— %s —', $intent));
            $io->table(
                ['Language', 'Tokens before', 'Tokens after', 'Saved', 'Saving %'],
                self::aggregateTableRows(array_values(array_filter(
                    $report->intentAggregates,
                    static fn (LanguageAggregate $a): bool => $a->intent === $intent,
                ))),
            );
        }
        $io->text('— mixed (persuasion + token_opt) —');
        $io->table(
            ['Language', 'Tokens before', 'Tokens after', 'Saved', 'Saving %'],
            self::aggregateTableRows([...$report->aggregates, $report->total]),
        );

        $io->section('4. Cost projection (ESTIMATED — 1,000,000 requests, dated snapshot, not an invoice)');
        $io->text('Pair-token totals × gpt-4o-mini. Tiny corpus. Persuasion and token_opt are split; mixed is still shown because they can cancel.');
        $million = array_values(array_filter(
            $report->projections,
            static fn (CostProjection $p): bool => $p->requests === 1_000_000,
        ));
        foreach (['persuasion', 'token_opt', 'mixed'] as $intent) {
            $io->text(sprintf('— %s —', $intent));
            $io->table(
                ['Lang', 'Input before', 'Input after', 'Input saved', 'Output before', 'Output after', 'Output saved'],
                array_map(static function (CostProjection $p): array {
                    return [
                        self::languageName($p->language),
                        self::money($p->inputBeforeUsd),
                        self::money($p->inputAfterUsd),
                        self::money($p->inputSavedUsd),
                        self::money($p->outputBeforeUsd),
                        self::money($p->outputAfterUsd),
                        self::money($p->outputSavedUsd),
                    ];
                }, array_values(array_filter(
                    $million,
                    static fn (CostProjection $p): bool => $p->intent === $intent,
                ))),
            );
        }

        $io->section(sprintf('5. Context-window copies (CALCULATED, window = %s tokens)', number_format($report->pricing->comparisonWindowTokens)));
        $io->text(self::contextCaption($report));
        $secondaryByLanguage = self::indexByLanguage($report->secondaryBaselines);
        $enUnits = null;
        foreach ($report->baselines as $baseline) {
            if ($baseline->language === 'en') {
                $enUnits = $baseline->contextUnits;
                break;
            }
        }
        $io->table(
            ['Language', $primaryLabel.' copies', 'vs EN', $secondaryLabel.' copies', 'vs EN', 'Lost vs EN ('.$primaryLabel.')'],
            array_map(static function (LanguageBaseline $b) use ($secondaryByLanguage, $enUnits): array {
                $secondary = $secondaryByLanguage[$b->language] ?? null;
                $lost = $enUnits === null ? 'n/a' : number_format($enUnits - $b->contextUnits, 2);

                return [
                    self::languageName($b->language),
                    number_format($b->contextUnits, 2),
                    self::capacityCell($b->capacityVsEn),
                    $secondary === null ? 'n/a' : number_format($secondary->contextUnits, 2),
                    $secondary === null ? 'n/a' : self::capacityCell($secondary->capacityVsEn),
                    $lost,
                ];
            }, $report->baselines),
        );

        $io->section('6. Regressions — replacement uses MORE tokens');
        if ($report->regressions === []) {
            $io->text('None. Every replacement used fewer or equal tokens.');
        } else {
            $io->table(
                ['Lang', 'Intent', 'Original', 'Replacement', 'Before', 'After', 'Delta'],
                array_map(static function (PairResult $r): array {
                    return [
                        $r->pair->language,
                        $r->pair->intent,
                        $r->pair->original,
                        $r->pair->replacement,
                        (string) $r->before->tokens,
                        (string) $r->after->tokens,
                        sprintf('%+d', $r->deltaTokens),
                    ];
                }, $report->regressions),
            );
        }

        $io->section('Flow trace');
        $io->listing($report->trace);
    }

    /**
     * @param list<LanguageAggregate> $aggregates
     *
     * @return list<list<string>>
     */
    private static function aggregateTableRows(array $aggregates): array
    {
        return array_map(static function (LanguageAggregate $aggregate): array {
            return [
                self::languageName($aggregate->language),
                (string) $aggregate->tokensBefore,
                (string) $aggregate->tokensAfter,
                (string) $aggregate->tokensSaved,
                sprintf('%+.2f%%', $aggregate->savingPercent),
            ];
        }, $aggregates);
    }

    /**
     * @return list<string>
     */
    private static function baselineCaption(BenchmarkReport $report): array
    {
        return [
            sprintf(
                '%s  —  MEASURED %s. Ratios CALCULATED.',
                self::formatSnapshot($report->baselines),
                self::encodingLabel($report->primaryTokenizer),
            ),
            sprintf(
                '%s  —  MEASURED %s. Same texts, different encoding — not a universal language tax.',
                self::formatSnapshot($report->secondaryBaselines),
                self::encodingLabel($report->secondaryTokenizer),
            ),
        ];
    }

    /**
     * @return list<string>
     */
    private static function contextCaption(BenchmarkReport $report): array
    {
        return [
            'How many copies of this parallel offer fit. Denominator is MEASURED passage tokens, not pair totals. Hypothetical fill — not a product context window.',
            sprintf(
                '%s  —  CALCULATED %s.',
                self::formatContextSnapshot($report->baselines),
                self::encodingLabel($report->primaryTokenizer),
            ),
            sprintf(
                '%s  —  CALCULATED %s. Encoding changes how many copies fit.',
                self::formatContextSnapshot($report->secondaryBaselines),
                self::encodingLabel($report->secondaryTokenizer),
            ),
        ];
    }

    /**
     * @param list<LanguageBaseline> $baselines
     */
    private static function formatContextSnapshot(array $baselines): string
    {
        $parts = [];
        foreach ($baselines as $baseline) {
            $copies = number_format($baseline->contextUnits, 2);
            if ($baseline->capacityVsEn === null || $baseline->language === 'en') {
                $parts[] = sprintf('%s %s', strtoupper($baseline->language), $copies);
                continue;
            }
            $parts[] = sprintf('%s %s (%+.2f%%)', strtoupper($baseline->language), $copies, $baseline->capacityVsEn - 100);
        }

        return implode('  ·  ', $parts);
    }

    /**
     * @param list<LanguageBaseline> $baselines
     */
    private static function formatSnapshot(array $baselines): string
    {
        $parts = [];
        foreach ($baselines as $baseline) {
            if ($baseline->premiumVsEn === null) {
                $parts[] = sprintf('%s %d', strtoupper($baseline->language), $baseline->tokens);
                continue;
            }
            $parts[] = sprintf('%s %d (%+.2f%%)', strtoupper($baseline->language), $baseline->tokens, $baseline->premiumVsEn);
        }

        return implode('  ·  ', $parts);
    }

    /**
     * @param list<LanguageBaseline> $baselines
     *
     * @return array<string, LanguageBaseline>
     */
    private static function indexByLanguage(array $baselines): array
    {
        $indexed = [];
        foreach ($baselines as $baseline) {
            $indexed[$baseline->language] = $baseline;
        }

        return $indexed;
    }

    private static function encodingLabel(string $tokenizer): string
    {
        $pos = strrpos($tokenizer, ':');

        return $pos === false ? $tokenizer : substr($tokenizer, $pos + 1);
    }

    private static function capacityCell(?float $capacity): string
    {
        if ($capacity === null) {
            return 'n/a';
        }
        if ($capacity === 100.0) {
            return 'baseline';
        }

        return sprintf('%+.2f%%', $capacity - 100);
    }

    private static function premiumCell(?float $premium): string
    {
        return $premium === null ? 'baseline' : sprintf('%+.2f%%', $premium);
    }

    private static function languageName(string $code): string
    {
        return match ($code) {
            'en' => 'English',
            'fr' => 'French',
            'de' => 'German',
            'all' => 'All',
            default => $code,
        };
    }

    private function renderValueSemantics(SymfonyStyle $io, BenchmarkState $state): void
    {
        $io->section('PHP values are not C pointers');
        $rows = [];
        foreach (ValueSemantics::demonstrate($state) as $row) {
            $rows[] = [$row['label'], $row['detail']];
        }
        $io->table(['Mechanism', 'What happened'], $rows);
    }

    private static function money(float $usd): string
    {
        $decimals = abs($usd) > 0 && abs($usd) < 0.01 ? 6 : 4;

        return sprintf('$%s', number_format($usd, $decimals));
    }
}
