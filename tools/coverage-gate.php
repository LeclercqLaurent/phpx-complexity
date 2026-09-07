<?php

declare(strict_types=1);

/**
 * Checks that a clover report reaches the coverage floor.
 * Factual: a counter compared to a threshold, never a grade.
 *
 * Usage: php tools/coverage-gate.php var/clover.xml 90
 */

$file = $argv[1] ?? '';
$minimum = (float) ($argv[2] ?? 90);

if (!is_file($file)) {
    fwrite(STDERR, sprintf("Coverage report not found: %s\n", $file));
    exit(2);
}

$xml = simplexml_load_file($file);
if (false === $xml) {
    fwrite(STDERR, sprintf("Coverage report is unreadable: %s\n", $file));
    exit(2);
}

$statements = 0;
$covered = 0;
foreach ($xml->xpath('//file/metrics') ?: [] as $metrics) {
    $statements += (int) $metrics['statements'];
    $covered += (int) $metrics['coveredstatements'];
}

if (0 === $statements) {
    fwrite(STDERR, "No statement measured.\n");
    exit(2);
}

$percent = 100 * $covered / $statements;
printf("   coverage: %.1f%% (%d/%d statements), floor %.0f%%\n", $percent, $covered, $statements, $minimum);

exit($percent + 0.05 < $minimum ? 1 : 0);
