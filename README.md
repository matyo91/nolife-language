# Nolife Language

Small Symfony CLI experiment: does the language we use with an LLM have a measurable token cost, and can wording change that cost without destroying meaning?

English is the baseline. French and German are measured on the same ideas. Tokens are counted with a real BPE tokenizer (`yethee/tiktoken`), not `characters / 4`.

## Run

```bash
composer install
php bin/console app:language:benchmark
```

Useful options:

```bash
php bin/console app:language:benchmark --language=en --language=fr
php bin/console app:language:benchmark --format=json
php bin/console app:language:benchmark --explain-values
```

JSON artifacts land in `var/benchmark/` (`latest.json` plus a timestamped copy).

Requires PHP ≥ 8.5. Orchestration uses `darkwood/flow`.

Corpus **1.0.2**. CLI section 1 prints both encodings on one EN/FR/DE grid. Pair totals are split by `persuasion` / `token_opt` / `mixed`.
