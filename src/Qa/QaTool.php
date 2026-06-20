<?php

declare(strict_types=1);

namespace PhpxComplexity\Qa;

/**
 * Description d'un outil de QA recherché dans un projet : il est considéré présent
 * s'il est déclaré dans composer.json (require / require-dev) OU si l'un de ses
 * fichiers de configuration existe à la racine du projet.
 */
final class QaTool
{
    /**
     * @param list<string> $packages noms de paquets Composer (minuscule)
     * @param list<string> $files    fichiers/dossiers de config à la racine
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $category,
        public readonly array $packages,
        public readonly array $files,
    ) {
    }
}
