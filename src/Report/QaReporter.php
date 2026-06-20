<?php

declare(strict_types=1);

namespace PhpxComplexity\Report;

use PhpxComplexity\Qa\QaToolResult;

/**
 * Rapport texte de présence des outils de QA, groupé par catégorie.
 */
final class QaReporter
{
    /**
     * @param list<QaToolResult> $results
     */
    public function render(array $results): string
    {
        $lines = ['Présence des outils de QA :'];

        $byCategory = [];
        foreach ($results as $result) {
            $byCategory[$result->tool->category][] = $result;
        }

        $present = 0;
        foreach ($byCategory as $category => $tools) {
            $lines[] = '  ' . $category;
            foreach ($tools as $result) {
                $mark = $result->present ? '✓' : '✗';
                $flag = $result->required && !$result->present ? ' (REQUIS, manquant)' : '';
                $evidence = $result->present ? ' [' . implode(', ', $result->evidence) . ']' : '';
                $lines[] = sprintf('    %s %s%s%s', $mark, $result->tool->label, $evidence, $flag);
                if ($result->present) {
                    ++$present;
                }
            }
        }

        $missingRequired = $this->missingRequired($results);
        $lines[] = sprintf('  → %d/%d outils détectés%s', $present, count($results), [] === $missingRequired ? '' : sprintf(', %d requis manquant(s)', count($missingRequired)));

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<QaToolResult> $results
     *
     * @return list<string>
     */
    public function missingRequired(array $results): array
    {
        $missing = [];
        foreach ($results as $result) {
            if ($result->required && !$result->present) {
                $missing[] = $result->tool->key;
            }
        }

        return $missing;
    }
}
