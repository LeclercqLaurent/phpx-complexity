<?php

declare(strict_types=1);

namespace PhpxComplexity\Baseline;

/**
 * Résultat d'une confrontation à l'instantané de référence.
 */
final class Comparison
{
    /**
     * @param list<MethodDelta>                        $deltas
     * @param list<MethodSnapshot>                     $appeared
     * @param list<MethodSnapshot>                     $disappeared
     * @param array<string,array{from:float,to:float}> $thresholdChanges
     */
    public function __construct(
        public readonly string $source,
        public readonly array $deltas,
        public readonly array $appeared,
        public readonly array $disappeared,
        public readonly array $thresholdChanges,
    ) {
    }

    /**
     * @return list<MethodDelta>
     */
    public function of(DeltaCategory $category): array
    {
        return array_values(array_filter(
            $this->deltas,
            static fn (MethodDelta $delta): bool => $delta->category === $category,
        ));
    }

    /**
     * Ce que le mode gate refuse : le code empire. Les violations héritées
     * inchangées, elles, passent — sinon la baseline ne servirait à rien.
     */
    public function regressionCount(): int
    {
        return count($this->of(DeltaCategory::NewViolation)) + count($this->of(DeltaCategory::Worsened));
    }
}
