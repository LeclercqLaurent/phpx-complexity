<?php

declare(strict_types=1);

namespace PhpxComplexity\Vcs\Exception;

use RuntimeException;

/**
 * Échec du seul module qui touche au réseau. Exception dédiée : l'appelant
 * distingue un problème de récupération d'un problème d'analyse.
 */
final class GitException extends RuntimeException
{
    public static function unsupportedUrl(string $url): self
    {
        return new self(sprintf(
            'URL de dépôt non supportée : %s (schémas acceptés : https://, ssh://, ou la forme git@hote:chemin).',
            $url,
        ));
    }

    public static function gitMissing(string $binary): self
    {
        return new self(sprintf('Binaire « %s » introuvable : la sous-commande fetch a besoin de git.', $binary));
    }

    public static function cloneFailed(string $url, string $details): self
    {
        return new self(rtrim(sprintf("Clonage impossible : %s\n%s", $url, $details)));
    }

    public static function temporaryDirectoryFailed(string $path): self
    {
        return new self(sprintf('Répertoire temporaire non créable : %s', $path));
    }
}
