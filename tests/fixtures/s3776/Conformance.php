<?php

declare(strict_types=1);

/**
 * S3776 conformance cases. The expected values are derived from the
 * SPECIFICATION (the SonarSource "Cognitive Complexity" white paper), never from
 * the output of our implementation, without which the test would validate
 *
 * This file is never executed: it is only analysed.
 */
final class Conformance
{
    public function noBranching(int $a): int
    {
        $b = $a * 2;
        $c = $b + 1;

        return $c;
    }

    // The canonical example from the white paper: for +1, nested for +2, if +3,
    // labelled jump +1.
    public function sumOfPrimes(int $max): int
    {
        $total = 0;
        foreach (range(1, $max) as $i) {
            foreach (range(2, $i) as $j) {
                if ($i % $j === 0) {
                    continue 2;
                }
            }
            $total += $i;
        }

        return $total;
    }

    // Exemple canonique du white paper : un switch, quel que soit le nombre de
    // case, costs only +1.
    public function getWords(int $number): string
    {
        switch ($number) {
            case 1: return 'one';
            case 2: return 'a couple';
            default: return 'lots';
        }
    }

    public function withElseif(int $a): string
    {
        if (1 === $a) {
            return 'un';
        } elseif (2 === $a) {
            return 'deux';
        } else {
            return 'autre';
        }
    }

    // PHP does not tell "else if" from "elseif": the same cost is expected.
    public function withElseSpaceIf(int $a): string
    {
        if (1 === $a) {
            return 'un';
        } else if (2 === $a) {
            return 'deux';
        } else {
            return 'autre';
        }
    }

    // An else takes NO nesting penalty: the reader stays at the same
    // level.
    public function elseAvoidsNestingPenalty(int $a, int $b): int
    {
        if ($a > 0) {
            if ($b > 0) {
                return 1;
            } else {
                return 2;
            }
        }

        return 0;
    }

    public function nestedConditions(array $rows): int
    {
        $n = 0;
        foreach ($rows as $row) {
            if ($row) {
                while ($row) {
                    --$row;
                    ++$n;
                }
            }
        }

        return $n;
    }

    // A sequence of identical operators counts only once.
    public function singleSequence(bool $a, bool $b, bool $c): bool
    {
        if ($a && $b && $c) {
            return true;
        }

        return false;
    }

    // Two distinct sequences: one in &&, one in ||.
    public function mixedSequences(bool $a, bool $b, bool $c, bool $d, bool $e): bool
    {
        if ($a && $b && $c || $d || $e) {
            return true;
        }

        return false;
    }

    // Negation adds nothing.
    public function negationIsFree(bool $a, bool $b): bool
    {
        if (!$a && !$b) {
            return true;
        }

        return false;
    }

    public function ternary(int $a): int
    {
        return $a > 0 ? 1 : 0;
    }

    public function nestedTernary(int $a, int $b): int
    {
        return $a > 0 ? ($b > 0 ? 1 : 2) : 0;
    }

    public function catchIncrements(): int
    {
        try {
            $this->run();
        } catch (RuntimeException $e) {
            return 1;
        } catch (LogicException $e) {
            return 2;
        }

        return 0;
    }

    public function catchNesting(array $rows): int
    {
        foreach ($rows as $row) {
            try {
                $this->run();
            } catch (RuntimeException $e) {
                continue;
            }
        }

        return 0;
    }

    // A nested function raises the level without an increment of its own.
    public function closureAddsNestingOnly(array $rows): array
    {
        return array_map(static function (int $row): int {
            if ($row > 0) {
                return $row;
            }

            return 0;
        }, $rows);
    }

    // break/continue with no level: a plain exit, no cost.
    public function simpleBreakIsFree(array $rows): int
    {
        foreach ($rows as $row) {
            if ($row) {
                break;
            }
        }

        return 0;
    }

    // break with a level: PHP's equivalent of the spec's "break LABEL".
    public function labelledBreakCounts(array $rows): int
    {
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if ($cell) {
                    break 2;
                }
            }
        }

        return 0;
    }

    public function gotoCounts(int $a): int
    {
        if ($a > 0) {
            goto fin;
        }
        fin:

        return 0;
    }

    // Postdates the spec: treated as a switch (a documented deviation).
    public function matchLikeSwitch(int $a): string
    {
        return match (true) {
            1 === $a => 'un',
            default => 'autre',
        };
    }

    private function run(): void
    {
    }
}
