<?php

declare(strict_types=1);

namespace PhpxComplexity\Vcs;

use PhpxComplexity\Vcs\Exception\GitException;

/**
 * URL de dépôt validée. C'est la frontière de sécurité du module : l'URL vient
 * de l'utilisateur et sera passée à git, donc rien ne franchit ce point sans
 * avoir été reconnu.
 *
 * Sont refusés : les transports non chiffrés ou non authentifiés (`git://`), les
 * chemins et `file://` — un audit se fait sur un dépôt distant, un dossier local
 * s'analyse directement —, et tout ce qui commence par un tiret, que git
 * prendrait pour une option.
 */
final class RepositoryUrl
{
    private const SCHEMES = ['https://', 'ssh://'];

    /**
     * Forme abrégée de SSH : utilisateur@hôte:chemin.
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
     * Nom lisible déduit de l'URL, pour que le dossier temporaire soit
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
