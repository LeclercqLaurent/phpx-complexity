<?php

declare(strict_types=1);

/**
 * Cas de conformité S107 (nombre de paramètres) et S1142 (points de sortie).
 * Jamais exécuté : seulement analysé.
 */
final class Signatures
{
    public function noParams(): void
    {
    }

    // Les propriétés promues restent des paramètres de la signature.
    public function __construct(private readonly int $a, private readonly string $b, public readonly bool $c)
    {
    }

    // Le variadique est UN paramètre, quel que soit le nombre d'arguments reçus.
    public function variadicCountsOnce(int $a, string ...$rest): void
    {
    }

    public function defaultsDoNotChangeCount(int $a, int $b = 1, int $c = 2): void
    {
    }

    public function noReturn(): void
    {
        $this->noParams();
    }

    public function singleReturn(int $a): int
    {
        return $a;
    }

    public function returnInEachBranch(int $a): int
    {
        if ($a > 0) {
            return 1;
        }
        if ($a < 0) {
            return -1;
        }

        return 0;
    }

    // Les return d'une fonction imbriquée appartiennent à celle-ci.
    public function returnsInClosureBelongToIt(array $rows): array
    {
        return array_map(static function (int $row): int {
            if ($row > 0) {
                return $row;
            }

            return 0;
        }, $rows);
    }

    // Le return implicite d'une fonction fléchée n'est pas celui de la méthode.
    public function arrowFunctionReturnExcluded(array $rows): array
    {
        return array_map(static fn (int $row): int => $row * 2, $rows);
    }

    public function returnsAcrossTryCatchFinally(): int
    {
        try {
            return 1;
        } catch (RuntimeException $e) {
            return 2;
        } finally {
            $this->noParams();
        }
    }
}
