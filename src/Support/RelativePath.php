<?php

declare(strict_types=1);

namespace PhpxComplexity\Support;

/**
 * Chemin d'un fichier relativement à la racine auditée.
 *
 * Sert autant à l'affichage qu'aux exclusions : ces dernières doivent porter sur
 * le chemin RELATIF, sans quoi un fragment comme `/var/` exclurait la totalité
 * d'un projet installé dans /var/www/… (cas courant sous Apache).
 */
final class RelativePath
{
    /**
     * Séparateurs normalisés en « / ». Renvoie le chemin complet si le fichier
     * n'est pas sous la racine.
     */
    public static function from(string $root, string $file): string
    {
        $base = rtrim(str_replace('\\', '/', is_file($root) ? \dirname($root) : $root), '/');
        $normalized = str_replace('\\', '/', $file);

        return str_starts_with($normalized, $base . '/') ? substr($normalized, strlen($base) + 1) : $normalized;
    }
}
