<?php

declare(strict_types=1);

namespace PhpxComplexity\Vcs\Exception;

use RuntimeException;

/**
 * A failure of the only module that touches the network. A dedicated exception,
 * so the caller can tell a fetch problem apart from an analysis problem.
 */
final class GitException extends RuntimeException
{
    public static function unsupportedUrl(string $url): self
    {
        return new self(sprintf(
            'Unsupported repository URL: %s (accepted schemes: https://, ssh://, or the git@host:path form).',
            $url,
        ));
    }

    public static function gitMissing(string $binary): self
    {
        return new self(sprintf('Binary "%s" not found: the fetch subcommand needs git.', $binary));
    }

    public static function cloneFailed(string $url, string $details): self
    {
        return new self(rtrim(sprintf("Clonage impossible : %s\n%s", $url, $details)));
    }

    public static function temporaryDirectoryFailed(string $path): self
    {
        return new self(sprintf('Cannot create temporary directory: %s', $path));
    }
}
