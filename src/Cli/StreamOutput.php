<?php

declare(strict_types=1);

namespace PhpxComplexity\Cli;

/**
 * The standard streams of the process: the real behaviour on the command line.
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
