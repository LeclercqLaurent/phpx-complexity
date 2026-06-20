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
 * Distinct de S107 : mesure la pression interne, pas la signature. Une factory
 * `restore()` à 8 champs peut avoir un pic élevé sans aucune logique réelle —
 * c'est pourquoi cette lentille se lit en regard de l'intrication.
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
            . '(7±2). Mesure la pression interne, pas la signature (≠ S107).';
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
