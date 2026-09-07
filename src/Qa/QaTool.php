<?php

declare(strict_types=1);

namespace PhpxComplexity\Qa;

/**
 * The description of a QA tool looked for in a project: it counts as present if
 * it is declared in composer.json (require / require-dev) OR if one of its
 * configuration files exists at the root of the project.
 */
final class QaTool
{
    /**
     * @param list<string> $packages noms de paquets Composer (minuscule)
     * @param list<string> $files    config files or directories at the root
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $category,
        public readonly array $packages,
        public readonly array $files,
    ) {
    }
}
