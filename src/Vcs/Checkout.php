<?php

declare(strict_types=1);

namespace PhpxComplexity\Vcs;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Copie de travail temporaire d'un dépôt récupéré.
 *
 * `root` est le dossier créé par l'outil et `path` la copie qu'il contient : la
 * suppression porte sur le premier, jamais sur un chemin fourni de l'extérieur.
 */
final class Checkout
{
    public function __construct(
        public readonly string $root,
        public readonly string $path,
    ) {
    }

    public function remove(): void
    {
        if (!is_dir($this->root)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($entries as $entry) {
            if ($entry instanceof SplFileInfo) {
                $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
            }
        }

        @rmdir($this->root);
    }
}
