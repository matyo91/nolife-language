<?php

declare(strict_types=1);

namespace App\Language;

/**
 * Honest-numbers labels. Never upgrade a value's wording.
 *
 * MEASURED    — produced by executing a tokenizer (or counting chars/words).
 * CALCULATED  — derived from measured integers (delta, %, premium).
 * ESTIMATED   — pricing projection or the bytes/4 heuristic. Not billed truth.
 */
final class Evidence
{
    public const MEASURED = 'MEASURED';
    public const CALCULATED = 'CALCULATED';
    public const ESTIMATED = 'ESTIMATED';
}
