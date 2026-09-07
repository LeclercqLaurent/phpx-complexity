<?php

declare(strict_types=1);

namespace PhpxComplexity\Vcs;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The temporary working copy of a fetched repository.
 *
 * `root` is the directory created by the tool and `path` the copy it holds:
 * removal targets the former, never a path supplied from outside.
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
