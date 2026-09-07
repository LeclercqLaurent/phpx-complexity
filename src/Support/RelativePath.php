<?php

declare(strict_types=1);

namespace PhpxComplexity\Support;

/**
 * The path of a file relative to the audited root.
 *
 * It serves display as much as exclusions, and exclusions must apply to the
 * RELATIVE path: otherwise a fragment such as `/var/` would exclude the whole
 * of a project installed in /var/www, which is common under Apache.
 */
final class RelativePath
{
    /**
     * Separators are normalised to "/". Returns the full path when the file
     * is not under the root.
     */
    public static function from(string $root, string $file): string
    {
        $base = rtrim(str_replace('\\', '/', is_file($root) ? \dirname($root) : $root), '/');
        $normalized = str_replace('\\', '/', $file);

        return str_starts_with($normalized, $base . '/') ? substr($normalized, strlen($base) + 1) : $normalized;
    }
}
