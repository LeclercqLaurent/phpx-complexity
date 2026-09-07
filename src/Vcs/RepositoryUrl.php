<?php

declare(strict_types=1);

namespace PhpxComplexity\Vcs;

use PhpxComplexity\Vcs\Exception\GitException;

/**
 * A validated repository URL. This is the security boundary of the module: the
 * URL comes from the user and will be handed to git, so nothing crosses this
 * point without having been recognised.
 *
 * Refused: unencrypted or unauthenticated transports (`git://`), plain paths and
 * `file://`, since an audit runs on a remote repository and a local directory
 * s'analyse directement —, et tout ce qui commence par un tiret, que git
 * prendrait pour une option.
 */
final class RepositoryUrl
{
    private const SCHEMES = ['https://', 'ssh://'];

    /**
     * The short SSH form: user@host:path.
     */
    private const SCP_LIKE = '#^[A-Za-z0-9._-]+@[A-Za-z0-9.-]+:[A-Za-z0-9._~/-]+$#';

    private function __construct(public readonly string $value)
    {
    }

    /**
     * @throws GitException
     */
    public static function fromString(string $url): self
    {
        $trimmed = trim($url);
        if (!self::isAccepted($trimmed)) {
            throw GitException::unsupportedUrl($url);
        }

        return new self($trimmed);
    }

    /**
     * A readable name derived from the URL, so the temporary directory is
     * identifiable quand on le conserve avec --keep.
     */
    public function name(): string
    {
        $path = rtrim(preg_replace('#[?\#].*$#', '', $this->value) ?? $this->value, '/');
        $base = basename($path, '.git');
        $safe = preg_replace('#[^A-Za-z0-9._-]#', '-', $base) ?? '';

        return '' === $safe ? 'depot' : $safe;
    }

    private static function isAccepted(string $url): bool
    {
        if ('' === $url || str_starts_with($url, '-')) {
            return false;
        }

        foreach (self::SCHEMES as $scheme) {
            if (str_starts_with($url, $scheme)) {
                return strlen($url) > strlen($scheme);
            }
        }

        return 1 === preg_match(self::SCP_LIKE, $url);
    }
}
