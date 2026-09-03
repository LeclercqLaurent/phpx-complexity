<?php

declare(strict_types=1);

/**
 * Cas de conformité S3776. Les valeurs attendues sont dérivées de la
 * SPÉCIFICATION (white paper SonarSource « Cognitive Complexity »), jamais de la
 * sortie de notre implémentation — sans quoi le test ne validerait rien.
 *
 * Ce fichier n'est jamais exécuté : il est seulement analysé.
 */
final class Conformance
{
    public function noBranching(int $a): int
    {
        $b = $a * 2;
        $c = $b + 1;

        return $c;
    }

    // Exemple canonique du white paper : for +1, for imbriqué +2, if +3,
    // saut étiqueté +1.
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
    // cas, ne coûte que +1.
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

    // PHP ne distingue pas « else if » de « elseif » : même coût attendu.
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

    // Un else ne subit PAS de pénalité d'imbrication : le lecteur reste au
    // même niveau.
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

    // Une séquence d'opérateurs identiques ne compte qu'une fois.
    public function singleSequence(bool $a, bool $b, bool $c): bool
    {
        if ($a && $b && $c) {
            return true;
        }

        return false;
    }

    // Deux séquences distinctes : une en &&, une en ||.
    public function mixedSequences(bool $a, bool $b, bool $c, bool $d, bool $e): bool
    {
        if ($a && $b && $c || $d || $e) {
            return true;
        }

        return false;
    }

    // La négation n'ajoute rien.
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

    // Une fonction imbriquée augmente le niveau sans incrément propre.
    public function closureAddsNestingOnly(array $rows): array
    {
        return array_map(static function (int $row): int {
            if ($row > 0) {
                return $row;
            }

            return 0;
        }, $rows);
    }

    // break/continue sans niveau : simple sortie, aucun coût.
    public function simpleBreakIsFree(array $rows): int
    {
        foreach ($rows as $row) {
            if ($row) {
                break;
            }
        }

        return 0;
    }

    // break avec niveau : équivalent PHP du « break LABEL » de la spec.
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

    // Postérieur à la spec : traité comme un switch (écart documenté).
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
