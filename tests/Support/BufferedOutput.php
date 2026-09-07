<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Support;

use PhpxComplexity\Cli\Output;

/**
 * Captures the CLI output so it can be inspected in memory.
 */
final class BufferedOutput implements Output
{
    public string $out = '';
    public string $err = '';

    public function write(string $text): void
    {
        $this->out .= $text;
    }

    public function error(string $line): void
    {
        $this->err .= $line . "\n";
    }

    public function all(): string
    {
        return $this->out . $this->err;
    }
}
