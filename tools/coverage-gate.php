<?php

declare(strict_types=1);

/**
 * Vérifie qu'un rapport clover atteint le plancher de couverture du socle.
 * Factuel : un compteur confronté à un seuil, jamais une note.
 *
 * Usage : php tools/coverage-gate.php var/clover.xml 90
 */

$file = $argv[1] ?? '';
$minimum = (float) ($argv[2] ?? 90);

if (!is_file($file)) {
    fwrite(STDERR, sprintf("Rapport de couverture introuvable : %s\n", $file));
    exit(2);
}

$xml = simplexml_load_file($file);
if (false === $xml) {
    fwrite(STDERR, sprintf("Rapport de couverture illisible : %s\n", $file));
    exit(2);
}

$statements = 0;
$covered = 0;
foreach ($xml->xpath('//file/metrics') ?: [] as $metrics) {
    $statements += (int) $metrics['statements'];
    $covered += (int) $metrics['coveredstatements'];
}

if (0 === $statements) {
    fwrite(STDERR, "Aucune instruction mesurée.\n");
    exit(2);
}

$percent = 100 * $covered / $statements;
printf("   couverture : %.1f %% (%d/%d instructions), plancher %.0f %%\n", $percent, $covered, $statements, $minimum);

exit($percent + 0.05 < $minimum ? 1 : 0);
