<?php

declare(strict_types=1);

namespace PhpxComplexity\Support;

/**
 * Résout la racine d'un projet : on part du chemin analysé et on remonte jusqu'au
 * premier dossier contenant composer.json (cas courant : on audite `app/src` mais
 * la config / les rapports vivent dans `app/`). À défaut, le dossier de départ.
 */
final class ProjectRoot
{
    public static function resolve(string $path): string
    {
        $dir = rtrim(str_replace('\\', '/', is_file($path) ? \dirname($path) : $path), '/');
        $current = $dir;
        while ('' !== $current && '/' !== $current) {
            if (is_file($current . '/composer.json')) {
                return $current;
            }
            $current = \dirname($current);
        }

        return $dir;
    }
}
