<?php

declare(strict_types=1);

namespace PhpxComplexity\Cli;

/**
 * Destination des sorties du CLI.
 *
 * Existing as a collaborator rather than as direct STDOUT calls is what allows
 * the behaviour of the application to be checked IN MEMORY: without this seam,
 * the only way to test it is to spawn a subprocess, which coverage tools
 * couverture n'instrumentent pas.
 */
interface Output
{
    public function write(string $text): void;

    /**
     * An error message; the trailing newline is added.
     */
    public function error(string $line): void;
}
