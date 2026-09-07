<?php

declare(strict_types=1);

namespace PhpxComplexity\Baseline;

/**
 * The result of a comparison against the reference snapshot.
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
     * What gate mode refuses: the code getting worse. Inherited violations that
     * have not moved pass, otherwise the baseline would serve no purpose.
     */
    public function regressionCount(): int
    {
        return count($this->of(DeltaCategory::NewViolation)) + count($this->of(DeltaCategory::Worsened));
    }
}
