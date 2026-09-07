<?php

declare(strict_types=1);

namespace PhpxComplexity\Qa;

/**
 * The detection result for a QA tool: present or not, along with the evidence
 * (Composer packages or config files) that motivated the detection.
 */
final class QaToolResult
{
    /**
     * @param list<string> $evidence
     */
    public function __construct(
        public readonly QaTool $tool,
        public readonly bool $present,
        public readonly array $evidence,
        public readonly bool $required,
    ) {
    }
}
