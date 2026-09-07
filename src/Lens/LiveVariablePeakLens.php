<?php

declare(strict_types=1);

namespace PhpxComplexity\Lens;

use PhpParser\Node;
use PhpxComplexity\Ast\AstHelper;

/**
 * Peak of live variables: the largest number of local variables whose lifetime
 * [first occurrence, last occurrence] overlaps a single line. A proxy for the
 * working-memory load imposed on the reader (see 7±2).
 *
 * Parameters USED in the body count, deliberately: an argument the reader has to
 * keep in mind does occupy their working memory. The lens is therefore NOT
 * orthogonal to S107; measured over 40,594 methods, it correlates more with the
 * parameter count (0.70) than with S3776 (0.64).
 *
 * The signal thus lies in the PAIR with entanglement: a `restore()` factory with
 * 8 fields has a high peak and zero entanglement, whereas a method that really
 * interweaves its variables has both. Excluding parameters from the computation
 * was measured (docs/lens-validation.md): it does decorrelate from S107
 * (0.29) but moves the lens closer to S3776 (0.71) and to entanglement (0.83).
 * The redundancy would be displaced, not removed.
 */
final class LiveVariablePeakLens implements Lens
{
    public function __construct(private readonly float $threshold = 8.0)
    {
    }

    public function key(): string
    {
        return 'live_peak';
    }

    public function label(): string
    {
        return 'Pic vivantes';
    }

    public function reference(): string
    {
        return '';
    }

    public function description(): string
    {
        return 'The largest number of local variables alive at the same time '
            . '(lifetime running from first to last use, overlapping a single '
            . "line). A proxy for the reader's working-memory load (7±2). Used "
            . 'parameters count: read it alongside entanglement, which alone '
            . 'separates a wide signature from real interweaving.';
    }

    public function measure(Node\FunctionLike $function, array $stmts): float
    {
        $spans = AstHelper::variableSpans($stmts);
        if ([] === $spans) {
            return 0.0;
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

    public function defaultThreshold(): float
    {
        return $this->threshold;
    }
}
