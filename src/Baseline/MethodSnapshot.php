<?php

declare(strict_types=1);

namespace PhpxComplexity\Baseline;

/**
 * A method as frozen in a snapshot: its identity and its raw values. Neither
 * percentile rank nor divergence, since those depend on the analysed batch and
 * would vary from run to run without the method having moved.
 */
final class MethodSnapshot
{
    /**
     * @param array<string,float> $metrics lens key => raw value
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
