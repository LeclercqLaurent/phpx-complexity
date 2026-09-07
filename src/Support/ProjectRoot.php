<?php

declare(strict_types=1);

namespace PhpxComplexity\Support;

/**
 * Resolves the root of a project: it starts from the analysed path and walks
 * up to the first directory holding a composer.json (a common case: auditing
 * `app/src` while the config and the reports live in `app/`). Failing that, the
 * starting directory.
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
