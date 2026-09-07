<?php

declare(strict_types=1);

namespace PhpxComplexity\Report;

use PhpxComplexity\Analyzer\MethodResult;
use PhpxComplexity\Audit\AuditResult;
use PhpxComplexity\Config\Config;
use PhpxComplexity\Lens\Lens;

/**
 * The readable text report: a ranking by dominant lens, threshold violations,
 * and the DIVERGENCE report (methods where the lenses contradict each other,
 * which is the heart of the tool). No external dependency, minimal ANSI output.
 */
final class ConsoleReporter
{
    /**
     * @param list<Lens> $lenses
     */
    public function __construct(
        private readonly array $lenses,
        private readonly Config $config,
    ) {
    }

    public function render(AuditResult $audit, bool $showDivergence): string
    {
        $results = $audit->results;
        $out = [];
        $out[] = sprintf('phpx-complexity: %d methods / %d files', count($results), $audit->files);
        $out[] = str_repeat('=', 60);
        $out[] = '';
        $out[] = $this->topTable($results);

        if ($showDivergence) {
            $out[] = '';
            $out[] = $this->divergenceSection($results);
        }

        $out[] = '';
        $out[] = $this->violationsSection($results);

        return implode("\n", $out) . "\n";
    }

    /**
     * @param list<MethodResult> $results
     */
    private function topTable(array $results): string
    {
        usort($results, fn (MethodResult $a, MethodResult $b) => $this->primary($b) <=> $this->primary($a));
        $rows = array_slice($results, 0, $this->config->top);

        $lines = ['Top ' . count($rows) . ' methods (all lenses):'];
        $lines[] = $this->header();
        foreach ($rows as $r) {
            $lines[] = $this->row($r);
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<MethodResult> $results
     */
    private function divergenceSection(array $results): string
    {
        // Actionable: a real threshold violation THAT the other lenses would let
        // through (high rank divergence). That is the blind spot.
        $candidates = array_filter(
            $results,
            fn (MethodResult $r) => $r->divergence >= 0.6 && $this->hasViolation($r),
        );
        usort($candidates, static fn (MethodResult $a, MethodResult $b) => $b->divergence <=> $a->divergence);
        $candidates = array_slice($candidates, 0, 15);

        $lines = ['Divergence: violations a single metric would let through:'];
        if ([] === $candidates) {
            $lines[] = '  (none)';

            return implode("\n", $lines);
        }
        $lines[] = $this->header();
        foreach ($candidates as $r) {
            $lines[] = $this->row($r) . sprintf('  Δ=%.2f %s', $r->divergence, $this->divergenceHint($r));
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<MethodResult> $results
     */
    private function violationsSection(array $results): string
    {
        $methodsInViolation = count(array_filter($results, fn (MethodResult $r) => $this->hasViolation($r)));
        $totalViolations = array_sum(array_map(fn (MethodResult $r) => $this->violationCount($r), $results));

        $lines = [sprintf(
            'Threshold violations: %d/%d methods affected (%d violation(s) in total):',
            $methodsInViolation,
            count($results),
            $totalViolations,
        )];
        $found = false;
        foreach ($this->lenses as $lens) {
            $key = $lens->key();
            $threshold = $this->config->threshold($key);
            $offenders = array_filter($results, static fn (MethodResult $r) => $r->metric($key) > $threshold);
            if ([] === $offenders) {
                continue;
            }
            $found = true;
            $lines[] = sprintf('  %s (%s > %s): %d', $lens->label(), $key, $this->num($threshold), count($offenders));
        }
        if (!$found) {
            $lines[] = '  (none)';
        }

        return implode("\n", $lines);
    }

    private function header(): string
    {
        $cells = ['LENS'];
        foreach ($this->lenses as $lens) {
            $cells[] = str_pad(strtoupper(substr($lens->key(), 0, 6)), 7);
        }

        return '  ' . implode(' ', array_map(static fn ($c) => str_pad($c, 7), array_slice($cells, 1)))
            . '  METHOD';
    }

    private function row(MethodResult $r): string
    {
        $cells = [];
        foreach ($this->lenses as $lens) {
            $value = $r->metric($lens->key());
            $flag = $value > $this->config->threshold($lens->key()) ? '!' : ' ';
            $cells[] = str_pad($this->num($value) . $flag, 7);
        }

        return '  ' . implode(' ', $cells) . sprintf('  %s::%s (l.%d)', $r->file, $r->name, $r->line);
    }

    private function hasViolation(MethodResult $r): bool
    {
        return $this->violationCount($r) > 0;
    }

    private function violationCount(MethodResult $r): int
    {
        $count = 0;
        foreach ($this->lenses as $lens) {
            if ($r->metric($lens->key()) > $this->config->threshold($lens->key())) {
                ++$count;
            }
        }

        return $count;
    }

    private function divergenceHint(MethodResult $r): string
    {
        $high = $low = null;
        foreach ($r->percentile as $key => $rank) {
            if (null === $high || $rank > $r->percentile[$high]) {
                $high = $key;
            }
            if (null === $low || $rank < $r->percentile[$low]) {
                $low = $key;
            }
        }

        return null === $high ? '' : sprintf('(↑%s ↓%s)', $high, $low);
    }

    private function primary(MethodResult $r): float
    {
        // The highest percentile rank across every lens: surfaces a method that
        // is extreme on any axis.
        return [] === $r->percentile ? 0.0 : max($r->percentile);
    }

    private function num(float $value): string
    {
        return floor($value) === $value ? (string) (int) $value : number_format($value, 2);
    }
}
