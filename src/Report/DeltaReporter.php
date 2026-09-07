<?php

declare(strict_types=1);

namespace PhpxComplexity\Report;

use PhpxComplexity\Baseline\Comparison;
use PhpxComplexity\Baseline\DeltaCategory;
use PhpxComplexity\Baseline\MethodDelta;

/**
 * The text report of the deltas against the reference snapshot.
 *
 * A delta is a fact ("cognitive 15 -> 18"), never a grade. Inherited violations
 * that have not moved do not appear: only movement is shown.
 */
final class DeltaReporter
{
    public function render(Comparison $comparison): string
    {
        $lines = [sprintf('Baseline: deltas against %s', $comparison->source)];
        $lines = array_merge(
            $lines,
            $this->block('New violations', $comparison->of(DeltaCategory::NewViolation)),
            $this->block('Worsened violations', $comparison->of(DeltaCategory::Worsened)),
            $this->block('Resolved violations', $comparison->of(DeltaCategory::Resolved)),
        );

        $lines[] = sprintf(
            '  Methods added: %d, removed: %d',
            count($comparison->appeared),
            count($comparison->disappeared),
        );

        foreach ($comparison->thresholdChanges as $lens => $change) {
            $lines[] = sprintf(
                '  Threshold "%s" changed since the snapshot: %s -> %s (raw values stay comparable)',
                $lens,
                $this->num($change['from']),
                $this->num($change['to']),
            );
        }

        $lines[] = sprintf('  -> %d regression(s)', $comparison->regressionCount());

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<MethodDelta> $deltas
     *
     * @return list<string>
     */
    private function block(string $label, array $deltas): array
    {
        if ([] === $deltas) {
            return [sprintf('  %s : aucune', $label)];
        }

        $lines = [sprintf('  %s (%d) :', $label, count($deltas))];
        foreach ($deltas as $delta) {
            $lines[] = sprintf(
                '    %-10s %6s → %-6s %s::%s',
                $delta->lens,
                null === $delta->before ? '—' : $this->num($delta->before),
                $this->num($delta->after),
                $delta->file,
                $delta->name,
            );
        }

        return $lines;
    }

    private function num(float $value): string
    {
        return floor($value) === $value ? (string) (int) $value : number_format($value, 2);
    }
}
