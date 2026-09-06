<?php

declare(strict_types=1);

namespace App\Language;

final readonly class ParallelPassage
{
    public function __construct(
        public string $id,
        public string $en,
        public string $fr,
        public string $de,
    ) {}

    /**
     * @param 'en'|'fr'|'de' $language
     */
    public function text(string $language): string
    {
        return match ($language) {
            'en' => $this->en,
            'fr' => $this->fr,
            'de' => $this->de,
        };
    }

    /**
     * @return array{id: string, en: string, fr: string, de: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'en' => $this->en,
            'fr' => $this->fr,
            'de' => $this->de,
        ];
    }
}
