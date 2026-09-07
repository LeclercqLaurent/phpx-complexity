<?php

declare(strict_types=1);

namespace PhpxComplexity\Coverage;

/**
 * The result of ingesting a coverage report. Coverage is an EXECUTION
 * measurement: the tool does not compute it, it reports what a report generated
 * by the project contains. No report means coverage is "not measured", never
 * 0%, which would be an unfounded verdict.
 */
final class CoverageReport
{
    private function __construct(
        public readonly bool $found,
        public readonly ?string $format,
        public readonly ?string $source,
        public readonly ?float $linePercent,
        public readonly ?int $linesCovered,
        public readonly ?int $linesValid,
        public readonly ?float $methodPercent,
    ) {
    }

    public static function notFound(): self
    {
        return new self(false, null, null, null, null, null, null);
    }

    public static function found(
        string $format,
        string $source,
        ?float $linePercent,
        ?int $linesCovered,
        ?int $linesValid,
        ?float $methodPercent,
    ): self {
        return new self(true, $format, $source, $linePercent, $linesCovered, $linesValid, $methodPercent);
    }
}
