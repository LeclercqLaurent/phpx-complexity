<?php

declare(strict_types=1);

/**
 * Aggregates the audits frozen by tools/corpus-study.sh and answers the two
 * que les garde-fous du projet posent aux lentilles maison :
 *
 *   1. NON-REDONDANCE — `live_peak` et `entangle` classent-ils autrement que
 *      S3776 ? Sinon ce sont des copies repeintes, donc du bruit.
 *   2. THRESHOLDS: do the invented values 8 and 4 hold up against the real
 *      distribution of published PHP code?
 *
 * It stays factual: distributions, rank correlations, counts. No score.
 */

require __DIR__ . '/stats.php';

const LENSES = ['cognitive', 'params', 'returns', 'live_peak', 'entangle'];
const THRESHOLDS = ['cognitive' => 15.0, 'params' => 7.0, 'returns' => 3.0, 'live_peak' => 8.0, 'entangle' => 3.0];

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
    printf("  %-20s %6d methods\n", $name, $count);
}
printf("  %-20s %6d methods, %d projects\n\n", 'TOTAL', $total, count($projects));

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

echo "\nRANK CORRELATION (Spearman): 1 = redundant, 0 = independent\n";
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
echo "  Among the methods each lens flags, the share S3776 does NOT\n";
echo "  flag, hence invisible to a single-metric linter.\n";
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
        "  %-10s %6d flagged, of which %6d off the S3776 radar (%5.1f%%)\n",
        $lens,
        $flagged,
        $missedByCognitive,
        0 === $flagged ? 0.0 : 100 * $missedByCognitive / $flagged,
    );
}
