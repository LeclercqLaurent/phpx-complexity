<?php

declare(strict_types=1);

/**
 * S107 (parameter count) and S1142 (exit points) conformance cases.
 * Never executed: only analysed.
 */
final class Signatures
{
    public function noParams(): void
    {
    }

    // Promoted properties remain parameters of the signature.
    public function __construct(private readonly int $a, private readonly string $b, public readonly bool $c)
    {
    }

    // A variadic is ONE parameter, whatever the number of arguments received.
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

    // The return statements of a nested function belong to that function.
    public function returnsInClosureBelongToIt(array $rows): array
    {
        return array_map(static function (int $row): int {
            if ($row > 0) {
                return $row;
            }

            return 0;
        }, $rows);
    }

    // The implicit return of an arrow function is not the method's own.
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
