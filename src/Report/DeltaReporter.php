<?php

declare(strict_types=1);

namespace PhpxComplexity\Report;

use PhpxComplexity\Baseline\Comparison;
use PhpxComplexity\Baseline\DeltaCategory;
use PhpxComplexity\Baseline\MethodDelta;

/**
 * Rapport texte des écarts à l'instantané de référence.
 *
 * Un écart est un fait — « cognitive 15 → 18 » — jamais une note. Les violations
 * héritées et inchangées n'apparaissent pas : seul le mouvement est montré.
 */
final class DeltaReporter
{
    public function render(Comparison $comparison): string
    {
        $lines = [sprintf('Baseline — écarts par rapport à %s :', $comparison->source)];
        $lines = array_merge(
            $lines,
            $this->block('Nouvelles violations', $comparison->of(DeltaCategory::NewViolation)),
            $this->block('Violations aggravées', $comparison->of(DeltaCategory::Worsened)),
            $this->block('Violations résolues', $comparison->of(DeltaCategory::Resolved)),
        );

        $lines[] = sprintf(
            '  Méthodes apparues : %d · disparues : %d',
            count($comparison->appeared),
            count($comparison->disappeared),
        );

        foreach ($comparison->thresholdChanges as $lens => $change) {
            $lines[] = sprintf(
                '  Seuil « %s » modifié depuis l\'instantané : %s → %s (les valeurs brutes restent comparables)',
                $lens,
                $this->num($change['from']),
                $this->num($change['to']),
            );
        }

        $lines[] = sprintf('  → %d régression(s)', $comparison->regressionCount());

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
