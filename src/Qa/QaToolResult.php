<?php

declare(strict_types=1);

namespace PhpxComplexity\Qa;

/**
 * Résultat de détection d'un outil de QA : présent ou non, et les preuves
 * (paquets Composer / fichiers de config) ayant motivé la détection.
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
