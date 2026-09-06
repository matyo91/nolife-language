<?php

declare(strict_types=1);

namespace App\Corpus;

use App\Language\ParallelPassage;
use App\Language\PhrasePair;

/**
 * Deterministic EN/FR/DE laboratory corpus.
 *
 * persuasion  — sales reframing (may cost MORE tokens).
 * token_opt   — semantically close shorter wording (may weaken persuasion).
 *
 * Never treat the cheaper side as the "best" wording.
 */
final class SalesCorpus
{
    public const VERSION = '1.0.2';

    /**
     * @return list<ParallelPassage>
     */
    public function passages(): array
    {
        return [
            new ParallelPassage(
                id: 'workshop-offer',
                en: 'Our workshop helps small teams ship a first working prototype in two weeks. You get a written plan, a working demo, and a recorded walkthrough you can share with stakeholders.',
                fr: 'Notre atelier aide les petites équipes à livrer un premier prototype fonctionnel en deux semaines. Vous repartez avec un plan écrit, une démo qui tourne, et une visite filmée à partager avec vos parties prenantes.',
                de: 'Unser Workshop hilft kleinen Teams, in zwei Wochen einen ersten funktionierenden Prototyp zu liefern. Sie erhalten einen schriftlichen Plan, eine laufende Demo und einen gefilmten Rundgang, den Sie mit Stakeholdern teilen können.',
            ),
            new ParallelPassage(
                id: 'price-line',
                en: 'The listed price is one thousand dollars for the standard plan.',
                fr: 'Le tarif affiché est de mille euros pour l’offre standard.',
                de: 'Der angegebene Preis beträgt eintausend Euro für den Standardtarif.',
            ),
        ];
    }

    /**
     * @return list<PhrasePair>
     */
    public function pairs(): array
    {
        return [
            // persuasion — English
            new PhrasePair('en', '$1,000', '$999', 'persuasion', 'price_anchor'),
            new PhrasePair('en', 'buy now', 'only three left', 'persuasion', 'scarcity'),
            new PhrasePair('en', 'basic', 'essential', 'persuasion', 'quality'),
            new PhrasePair('en', 'standard', 'customized', 'persuasion', 'specificity'),
            new PhrasePair('en', 'few', 'limited', 'persuasion', 'scarcity'),
            new PhrasePair('en', 'cost', 'investment', 'persuasion', 'framing'),

            // persuasion — French (idiomatic, not word-for-word)
            new PhrasePair('fr', '1 000 €', '999 €', 'persuasion', 'price_anchor'),
            new PhrasePair('fr', 'achetez maintenant', 'plus que trois en stock', 'persuasion', 'scarcity'),
            new PhrasePair('fr', 'basique', 'essentiel', 'persuasion', 'quality'),
            new PhrasePair('fr', 'standard', 'sur mesure', 'persuasion', 'specificity'),
            new PhrasePair('fr', 'quelques', 'stock limité', 'persuasion', 'scarcity'),
            new PhrasePair('fr', 'coût', 'investissement', 'persuasion', 'framing'),

            // persuasion — German (idiomatic)
            new PhrasePair('de', '1.000 €', '999 €', 'persuasion', 'price_anchor'),
            new PhrasePair('de', 'jetzt kaufen', 'nur noch drei verfügbar', 'persuasion', 'scarcity'),
            new PhrasePair('de', 'Basis', 'unverzichtbar', 'persuasion', 'quality'),
            new PhrasePair('de', 'Standard', 'maßgeschneidert', 'persuasion', 'specificity'),
            new PhrasePair('de', 'wenige', 'begrenzt', 'persuasion', 'scarcity'),
            new PhrasePair('de', 'Kosten', 'Investition', 'persuasion', 'framing'),

            // token_opt — same idea, cheaper-looking wording (may lose sales force)
            new PhrasePair('en', 'only three left', '3 left', 'token_opt', 'scarcity'),
            new PhrasePair('en', 'customized', 'custom', 'token_opt', 'specificity'),
            new PhrasePair('en', 'investment', 'cost', 'token_opt', 'framing'),
            new PhrasePair('fr', 'plus que trois en stock', '3 restants', 'token_opt', 'scarcity'),
            new PhrasePair('fr', 'sur mesure', 'adapté', 'token_opt', 'specificity'),
            new PhrasePair('fr', 'investissement', 'coût', 'token_opt', 'framing'),
            new PhrasePair('de', 'nur noch drei verfügbar', '3 übrig', 'token_opt', 'scarcity'),
            new PhrasePair('de', 'maßgeschneidert', 'passend', 'token_opt', 'specificity'),
            new PhrasePair('de', 'Investition', 'Kosten', 'token_opt', 'framing'),
        ];
    }

    /**
     * @param list<string> $languages
     *
     * @return list<PhrasePair>
     */
    public function pairsFor(array $languages): array
    {
        return array_values(array_filter(
            $this->pairs(),
            static fn (PhrasePair $pair): bool => in_array($pair->language, $languages, true),
        ));
    }

    /**
     * @return array{version: string, hash: string, passages: list<array<string, string>>, pairs: list<array<string, string>>}
     */
    public function snapshot(): array
    {
        $payload = [
            'version' => self::VERSION,
            'passages' => array_map(static fn (ParallelPassage $p): array => $p->toArray(), $this->passages()),
            'pairs' => array_map(static fn (PhrasePair $p): array => $p->toArray(), $this->pairs()),
        ];

        $canonical = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $payload['hash'] = hash('sha256', $canonical);

        return $payload;
    }
}
