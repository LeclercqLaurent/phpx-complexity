<?php

declare(strict_types=1);

namespace PhpxComplexity\Baseline;

/**
 * Écart constaté sur une lentille d'une méthode. Purement factuel : deux valeurs
 * brutes et leur nature, jamais de note ni de pondération.
 */
final class MethodDelta
{
    public function __construct(
        public readonly DeltaCategory $category,
        public readonly string $file,
        public readonly string $name,
        public readonly string $lens,
        public readonly ?float $before,
        public readonly float $after,
    ) {
    }
}
