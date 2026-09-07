<?php

declare(strict_types=1);

namespace PhpxComplexity\Baseline\Exception;

use RuntimeException;

/**
 * An unusable baseline. An exception dedicated to the module, so the caller can
 * tell a broken snapshot apart from a genuine analysis error.
 */
final class BaselineException extends RuntimeException
{
    public static function unreadable(string $file): self
    {
        return new self(sprintf('Baseline illisible : %s', $file));
    }

    public static function malformed(string $file): self
    {
        return new self(sprintf(
            'Invalid baseline: %s (a phpx-complexity "--json" output is expected).',
            $file,
        ));
    }
}
