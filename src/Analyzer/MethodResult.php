<?php

declare(strict_types=1);

namespace PhpxComplexity\Analyzer;

/**
 * The analysis result of a method or function: raw values per lens, plus the
 * percentile ranks and the divergence gap computed across the project.
 */
final class MethodResult
{
    /**
     * @param array<string,float> $metrics    lens key => raw value
     * @param array<string,float> $percentile lens key => percentile rank [0,1]
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
