<?php

declare(strict_types=1);

namespace PhpxComplexity\Baseline;

/**
 * Une méthode telle que figée dans un instantané : son identité et ses valeurs
 * brutes. Ni rang centile ni divergence — ceux-ci dépendent du lot analysé et
 * varieraient d'un run à l'autre sans que la méthode ait bougé.
 */
final class MethodSnapshot
{
    /**
     * @param array<string,float> $metrics clé de lentille => valeur brute
     */
    public function __construct(
        public readonly string $key,
        public readonly string $file,
        public readonly string $name,
        public readonly int $line,
        public readonly array $metrics,
    ) {
    }

    public function metric(string $lensKey): ?float
    {
        return $this->metrics[$lensKey] ?? null;
    }
}
