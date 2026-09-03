<?php

declare(strict_types=1);

namespace PhpxComplexity\Cli;

/**
 * Sorties standard du processus : le comportement réel en ligne de commande.
 */
final class StreamOutput implements Output
{
    public function write(string $text): void
    {
        fwrite(\STDOUT, $text);
    }

    public function error(string $line): void
    {
        fwrite(\STDERR, $line . "\n");
    }
}
