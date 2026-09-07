<?php

declare(strict_types=1);

/**
 * Statistics shared by the corpus study tools.
 */

/**
 * @param list<float> $sorted sorted values
 */
function percentile(array $sorted, float $q): float
{
    $n = count($sorted);

    return 0 === $n ? 0.0 : $sorted[min($n - 1, (int) floor($q * $n))];
}

/**
 * The share of values strictly below $value, that is the position of a threshold
 * la distribution.
 *
 * @param list<float> $sorted
 */
function quantileOf(array $sorted, float $value): float
{
    $below = 0;
    foreach ($sorted as $v) {
        if ($v >= $value) {
            break;
        }
        ++$below;
    }

    return 0 === count($sorted) ? 0.0 : $below / count($sorted);
}

function fmt(float $value): string
{
    return floor($value) === $value ? (string) (int) $value : number_format($value, 2);
}

/**
 * Ranks with ties averaged.
 *
 * @param list<float> $values
 *
 * @return list<float>
 */
function ranks(array $values): array
{
    $n = count($values);
    $order = range(0, $n - 1);
    usort($order, static fn (int $a, int $b): int => $values[$a] <=> $values[$b]);

    $ranks = array_fill(0, $n, 0.0);
    $i = 0;
    while ($i < $n) {
        $j = $i;
        while ($j + 1 < $n && $values[$order[$j + 1]] === $values[$order[$i]]) {
            ++$j;
        }
        $average = ($i + $j) / 2 + 1;
        for ($k = $i; $k <= $j; ++$k) {
            $ranks[$order[$k]] = $average;
        }
        $i = $j + 1;
    }

    return $ranks;
}

/**
 * @param list<float> $x
 * @param list<float> $y
 */
function pearson(array $x, array $y): float
{
    $n = count($x);
    $meanX = array_sum($x) / $n;
    $meanY = array_sum($y) / $n;
    $covariance = 0.0;
    $varianceX = 0.0;
    $varianceY = 0.0;
    for ($i = 0; $i < $n; ++$i) {
        $dx = $x[$i] - $meanX;
        $dy = $y[$i] - $meanY;
        $covariance += $dx * $dy;
        $varianceX += $dx * $dx;
        $varianceY += $dy * $dy;
    }

    return 0.0 === $varianceX || 0.0 === $varianceY ? 0.0 : $covariance / sqrt($varianceX * $varianceY);
}

/**
 * Rank correlation (Spearman).
 *
 * @param list<float> $x
 * @param list<float> $y
 */
function spearman(array $x, array $y): float
{
    return pearson(ranks($x), ranks($y));
}
