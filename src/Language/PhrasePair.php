<?php

declare(strict_types=1);

namespace App\Language;

final readonly class PhrasePair
{
    /**
     * @param 'en'|'fr'|'de'           $language
     * @param 'persuasion'|'token_opt' $intent
     */
    public function __construct(
        public string $language,
        public string $original,
        public string $replacement,
        public string $intent,
        public string $category,
    ) {}

    /**
     * @return array{language: string, original: string, replacement: string, intent: string, category: string}
     */
    public function toArray(): array
    {
        return [
            'language' => $this->language,
            'original' => $this->original,
            'replacement' => $this->replacement,
            'intent' => $this->intent,
            'category' => $this->category,
        ];
    }
}
