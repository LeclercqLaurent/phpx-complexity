<?php

declare(strict_types=1);

namespace PhpxComplexity\Analyzer;

/**
 * Résultat d'analyse d'une méthode/fonction : valeurs brutes par lentille, plus
 * les rangs centiles et l'écart de divergence calculés à l'échelle du projet.
 */
final class MethodResult
{
    /**
     * @param array<string,float> $metrics    clé de lentille => valeur brute
     * @param array<string,float> $percentile clé de lentille => rang centile [0,1]
     */
    public function __construct(
        public readonly string $file,
        public readonly string $name,
        public readonly int $line,
        public readonly array $metrics,
        public array $percentile = [],
        public float $divergence = 0.0,
    ) {
    }

    public function metric(string $lensKey): float
    {
        return $this->metrics[$lensKey] ?? 0.0;
    }
}
