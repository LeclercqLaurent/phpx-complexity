<?php

declare(strict_types=1);

namespace PhpxComplexity\Lens;

use PhpParser\Node;
use PhpxComplexity\Ast\AstHelper;

/**
 * Pic de variables vivantes : nombre maximal de variables locales dont la durée
 * de vie [première occurrence, dernière occurrence] recouvre une même ligne.
 * Proxy de la charge en mémoire de travail imposée au lecteur (cf. 7±2).
 *
 * Les paramètres UTILISÉS dans le corps comptent, et c'est assumé : un argument
 * qu'il faut garder en tête occupe bel et bien la mémoire de travail du lecteur.
 * La lentille n'est donc PAS orthogonale à S107 — mesurée sur 40 594 méthodes,
 * elle corrèle davantage avec le nombre de paramètres (0,70) qu'avec S3776
 * (0,64).
 *
 * Le signal tient donc à la PAIRE avec l'intrication : une factory `restore()` à
 * 8 champs a un pic élevé et une intrication nulle, une méthode qui entremêle
 * réellement ses variables a les deux. Exclure les paramètres du calcul a été
 * mesuré (docs/validation-lentilles.md) : cela décorrèle de S107 (0,29) mais
 * rapproche la lentille de S3776 (0,71) et de l'intrication (0,83). La
 * redondance serait déplacée, pas supprimée.
 */
final class LiveVariablePeakLens implements Lens
{
    public function __construct(private readonly float $threshold = 8.0)
    {
    }

    public function key(): string
    {
        return 'live_peak';
    }

    public function label(): string
    {
        return 'Pic vivantes';
    }

    public function reference(): string
    {
        return '';
    }

    public function description(): string
    {
        return 'Nombre maximal de variables locales « vivantes » en même temps '
            . '(durée de vie = de la première à la dernière utilisation, recouvrant '
            . 'une même ligne). Proxy de la charge en mémoire de travail du lecteur '
            . '(7±2). Les paramètres utilisés comptent : à lire en regard de '
            . "l'intrication, qui seule sépare une signature large d'un "
            . 'enchevêtrement réel.';
    }

    public function measure(Node\FunctionLike $function, array $stmts): float
    {
        $spans = AstHelper::variableSpans($stmts);
        if ([] === $spans) {
            return 0.0;
        }

        $delta = [];
        foreach ($spans as [$start, $end]) {
            $delta[$start] = ($delta[$start] ?? 0) + 1;
            $delta[$end + 1] = ($delta[$end + 1] ?? 0) - 1;
        }
        ksort($delta);

        $current = 0;
        $peak = 0;
        foreach ($delta as $d) {
            $current += $d;
            $peak = max($peak, $current);
        }

        return (float) $peak;
    }

    public function defaultThreshold(): float
    {
        return $this->threshold;
    }
}
