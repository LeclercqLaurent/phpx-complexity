<?php

declare(strict_types=1);

/**
 * Agrège les audits figés par tools/corpus-study.sh et répond aux deux questions
 * que les garde-fous du projet posent aux lentilles maison :
 *
 *   1. NON-REDONDANCE — `live_peak` et `entangle` classent-ils autrement que
 *      S3776 ? Sinon ce sont des copies repeintes, donc du bruit.
 *   2. SEUILS — les valeurs 8 et 4, inventées, se défendent-elles face à la
 *      distribution réelle du code PHP publié ?
 *
 * Reste factuel : distributions, corrélations de rangs, effectifs. Aucun score.
 */

const LENSES = ['cognitive', 'params', 'returns', 'live_peak', 'entangle'];
const THRESHOLDS = ['cognitive' => 15.0, 'params' => 7.0, 'returns' => 3.0, 'live_peak' => 8.0, 'entangle' => 4.0];

$directory = dirname(__DIR__) . '/var/corpus';
$files = glob($directory . '/*.json') ?: [];
if ([] === $files) {
    fwrite(STDERR, "Corpus vide : lancer d'abord tools/corpus-study.sh\n");
    exit(2);
}

/** @var array<string,list<float>> $columns */
$columns = array_fill_keys(LENSES, []);
$projects = [];

foreach ($files as $file) {
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data) || !is_array($data['methods'] ?? null)) {
        continue;
    }
    $projects[basename($file, '.json')] = count($data['methods']);
    foreach ($data['methods'] as $method) {
        foreach (LENSES as $lens) {
            $columns[$lens][] = (float) ($method['metrics'][$lens] ?? 0);
        }
    }
}

$total = count($columns['cognitive']);

echo "CORPUS\n";
foreach ($projects as $name => $count) {
    printf("  %-20s %6d méthodes\n", $name, $count);
}
printf("  %-20s %6d méthodes, %d projets\n\n", 'TOTAL', $total, count($projects));

echo "DISTRIBUTION PAR LENTILLE (valeurs brutes)\n";
printf("  %-10s %7s %7s %7s %7s %7s %9s %10s\n", 'lentille', 'p50', 'p75', 'p90', 'p95', 'p99', 'seuil', '% > seuil');
foreach (LENSES as $lens) {
    $sorted = $columns[$lens];
    sort($sorted);
    $over = count(array_filter($sorted, static fn (float $v): bool => $v > THRESHOLDS[$lens]));
    printf(
        "  %-10s %7s %7s %7s %7s %7s %9s %8.2f%%\n",
        $lens,
        fmt(percentile($sorted, 0.50)),
        fmt(percentile($sorted, 0.75)),
        fmt(percentile($sorted, 0.90)),
        fmt(percentile($sorted, 0.95)),
        fmt(percentile($sorted, 0.99)),
        fmt(THRESHOLDS[$lens]),
        100 * $over / max(1, $total),
    );
}

echo "\nCORRÉLATION DE RANGS (Spearman) — 1 = redondant, 0 = indépendant\n";
$ranks = [];
foreach (LENSES as $lens) {
    $ranks[$lens] = ranks($columns[$lens]);
}
printf("  %-10s", '');
foreach (LENSES as $lens) {
    printf('%11s', substr($lens, 0, 10));
}
echo "\n";
foreach (LENSES as $a) {
    printf('  %-10s', $a);
    foreach (LENSES as $b) {
        printf('%11.3f', pearson($ranks[$a], $ranks[$b]));
    }
    echo "\n";
}

echo "\nANGLE MORT AUX SEUILS COURANTS\n";
echo "  Parmi les méthodes que chaque lentille signale, part que S3776 ne\n";
echo "  signale PAS — donc invisible pour un linter mono-métrique.\n";
foreach (LENSES as $lens) {
    if ('cognitive' === $lens) {
        continue;
    }
    $flagged = 0;
    $missedByCognitive = 0;
    for ($i = 0; $i < $total; ++$i) {
        if ($columns[$lens][$i] <= THRESHOLDS[$lens]) {
            continue;
        }
        ++$flagged;
        if ($columns['cognitive'][$i] <= THRESHOLDS['cognitive']) {
            ++$missedByCognitive;
        }
    }
    printf(
        "  %-10s %6d signalées, dont %6d hors radar S3776 (%5.1f%%)\n",
        $lens,
        $flagged,
        $missedByCognitive,
        0 === $flagged ? 0.0 : 100 * $missedByCognitive / $flagged,
    );
}

/**
 * @param list<float> $sorted
 */
function percentile(array $sorted, float $q): float
{
    $n = count($sorted);

    return 0 === $n ? 0.0 : $sorted[min($n - 1, (int) floor($q * $n))];
}

function fmt(float $value): string
{
    return floor($value) === $value ? (string) (int) $value : number_format($value, 2);
}

/**
 * Rangs avec égalités moyennées.
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
