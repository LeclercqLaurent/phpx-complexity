<?php

declare(strict_types=1);

namespace PhpxComplexity\Baseline\Exception;

use RuntimeException;

/**
 * Baseline inutilisable. Exception dédiée au module : l'appelant distingue ainsi
 * un instantané défectueux d'une véritable erreur d'analyse.
 */
final class BaselineException extends RuntimeException
{
    public static function unreadable(string $file): self
    {
        return new self(sprintf('Baseline illisible : %s', $file));
    }

    public static function malformed(string $file): self
    {
        return new self(sprintf(
            'Baseline invalide : %s (attendu une sortie « --json » de phpx-complexity).',
            $file,
        ));
    }
}
