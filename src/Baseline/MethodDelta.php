<?php

declare(strict_types=1);

namespace PhpxComplexity\Baseline;

/**
 * A delta observed on one lens of one method. Purely factual: two raw values
 * and their nature, never a grade nor a weighting.
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
