<?php

declare(strict_types=1);

namespace App\Language;

final readonly class TokenCount
{
    public function __construct(
        public string $tokenizer,
        public int $tokens,
        public int $words,
        public int $characters,
        public int $bytes,
        public string $evidence = Evidence::MEASURED,
    ) {}

    /**
     * @return array{tokenizer: string, tokens: int, words: int, characters: int, bytes: int, evidence: string}
     */
    public function toArray(): array
    {
        return [
            'tokenizer' => $this->tokenizer,
            'tokens' => $this->tokens,
            'words' => $this->words,
            'characters' => $this->characters,
            'bytes' => $this->bytes,
            'evidence' => $this->evidence,
        ];
    }
}
