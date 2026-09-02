<?php

declare(strict_types=1);

/**
 * Éprouve une VARIANTE de `live_peak` qui exclut les paramètres du calcul de
 * vivacité.
 *
 * Motivation : l'étude du corpus a montré que `live_peak` corrèle davantage avec
 * `params` (0,703) qu'avec `cognitive` (0,637), alors que sa documentation
 * affirme mesurer « la pression interne, pas la signature ». Un paramètre
 * utilisé dans le corps est en effet une variable vivante.
 *
 * La variante ne compte que les variables INTRODUITES par le corps. Question
 * posée : cela décorrèle-t-il réellement de S107, et à quel prix ?
 *
 * Rien n'est modifié dans src/ : c'est une mesure, pas un changement.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/stats.php';

use PhpParser\Node;
use PhpxComplexity\Analyzer\ProjectAnalyzer;
use PhpxComplexity\Ast\AstHelper;
use PhpxComplexity\Config\Config;
use PhpxComplexity\Lens\CognitiveComplexityLens;
use PhpxComplexity\Lens\EntanglementLens;
use PhpxComplexity\Lens\Lens;
use PhpxComplexity\Lens\LiveVariablePeakLens;
use PhpxComplexity\Lens\ParameterCountLens;

/**
 * Pic de variables vivantes, paramètres exclus.
 */
final class InternalLivePeakLens implements Lens
{
    public function key(): string
    {
        return 'live_internal';
    }

    public function label(): string
    {
        return 'Pic interne';
    }

    public function reference(): string
    {
        return '';
    }

    public function description(): string
    {
        return 'Pic de variables vivantes, paramètres exclus : ne compte que les '
            . 'variables introduites par le corps de la méthode.';
    }

    public function defaultThreshold(): float
    {
        return 8.0;
    }

    public function measure(Node\FunctionLike $function, array $stmts): float
    {
        $spans = AstHelper::variableSpans($stmts);
        foreach ($function->getParams() as $param) {
            if ($param->var instanceof Node\Expr\Variable && is_string($param->var->name)) {
                unset($spans[$param->var->name]);
            }
        }

        $delta = [];
        foreach ($spans as [$start, $end]) {
            $delta[$start] = ($delta[$start] ?? 0) + 1;
            $delta[$end + 1] = ($delta[$end + 1] ?? 0) - 1;
        }
        ksort($delta);

        $current = 0;
        $peak = 0;
        foreach ($delta as $d) {
            $current += $d;
            $peak = max($peak, $current);
        }

        return (float) $peak;
    }
}

const KEYS = ['cognitive', 'params', 'live_peak', 'live_internal', 'entangle'];
const LIVE_PEAK_THRESHOLD = 8.0;
const COGNITIVE_THRESHOLD = 15.0;

$sources = glob(dirname(__DIR__) . '/var/corpus-src/*', GLOB_ONLYDIR) ?: [];
if ([] === $sources) {
    fwrite(STDERR, "Corpus absent : lancer d'abord tools/corpus-study.sh\n");
    exit(2);
}

$config = Config::defaults()->withOverrides(['exclude' => [
    '/vendor/', '/node_modules/', '/.git/', '/tests/', '/Tests/', '/test/',
    '/spec/', '/fixtures/', '/Fixtures/', '/stubs/', '/Stubs/',
]]);
$lenses = [
    new CognitiveComplexityLens(),
    new ParameterCountLens(),
    new LiveVariablePeakLens(),
    new InternalLivePeakLens(),
    new EntanglementLens(),
];

/** @var array<string,list<float>> $columns */
$columns = array_fill_keys(KEYS, []);
foreach ($sources as $source) {
    fwrite(STDERR, sprintf("  %s\n", basename($source)));
    $analysis = (new ProjectAnalyzer($lenses, $config))->analyze($source);
    foreach ($analysis['results'] as $result) {
        foreach (KEYS as $key) {
            $columns[$key][] = $result->metric($key);
        }
    }
}

$total = count($columns['cognitive']);
printf("\n%d méthodes, %d projets\n\n", $total, count($sources));

// Seuil de la variante placé au MÊME centile que live_peak > 8, pour que la
// comparaison porte sur la mesure et non sur une sévérité différente.
$sortedPeak = $columns['live_peak'];
sort($sortedPeak);
$quantile = quantileOf($sortedPeak, LIVE_PEAK_THRESHOLD);
$sortedInternal = $columns['live_internal'];
sort($sortedInternal);
$internalThreshold = percentile($sortedInternal, $quantile);

printf("SEUILS COMPARÉS AU MÊME CENTILE (p%.1f)\n", 100 * $quantile);
printf("  live_peak      > %s\n", fmt(LIVE_PEAK_THRESHOLD));
printf("  live_internal  > %s\n\n", fmt($internalThreshold));

echo "DISTRIBUTION\n";
printf("  %-14s %6s %6s %6s %6s %6s\n", '', 'p50', 'p75', 'p90', 'p95', 'p99');
foreach (['live_peak', 'live_internal'] as $key) {
    $sorted = $columns[$key];
    sort($sorted);
    printf(
        "  %-14s %6s %6s %6s %6s %6s\n",
        $key,
        fmt(percentile($sorted, 0.50)),
        fmt(percentile($sorted, 0.75)),
        fmt(percentile($sorted, 0.90)),
        fmt(percentile($sorted, 0.95)),
        fmt(percentile($sorted, 0.99)),
    );
}

echo "\nCORRÉLATION DE RANGS\n";
printf("  %-14s %10s %10s %10s\n", '', 'params', 'cognitive', 'entangle');
foreach (['live_peak', 'live_internal'] as $key) {
    printf(
        "  %-14s %10.3f %10.3f %10.3f\n",
        $key,
        spearman($columns[$key], $columns['params']),
        spearman($columns[$key], $columns['cognitive']),
        spearman($columns[$key], $columns['entangle']),
    );
}
printf("  %-14s %10.3f\n", 'entre variantes', spearman($columns['live_peak'], $columns['live_internal']));

echo "\nRENDEMENT AU SEUIL ÉQUIVALENT\n";
foreach ([['live_peak', LIVE_PEAK_THRESHOLD], ['live_internal', $internalThreshold]] as [$key, $threshold]) {
    $flagged = 0;
    $blindToCognitive = 0;
    $blindToBoth = 0;
    for ($i = 0; $i < $total; ++$i) {
        if ($columns[$key][$i] <= $threshold) {
            continue;
        }
        ++$flagged;
        $missedByCognitive = $columns['cognitive'][$i] <= COGNITIVE_THRESHOLD;
        $missedByParams = $columns['params'][$i] <= 7.0;
        $blindToCognitive += $missedByCognitive ? 1 : 0;
        $blindToBoth += $missedByCognitive && $missedByParams ? 1 : 0;
    }
    printf(
        "  %-14s %5d signalées · hors S3776 : %4d (%4.1f%%) · hors S3776 ET S107 : %4d (%4.1f%%)\n",
        $key,
        $flagged,
        $blindToCognitive,
        0 === $flagged ? 0.0 : 100 * $blindToCognitive / $flagged,
        $blindToBoth,
        0 === $flagged ? 0.0 : 100 * $blindToBoth / $flagged,
    );
}
