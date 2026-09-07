<?php

declare(strict_types=1);

namespace PhpxComplexity\Report;

use PhpxComplexity\Qa\QaToolResult;

/**
 * The text report of QA tool presence, grouped by category.
 */
final class QaReporter
{
    /**
     * @param list<QaToolResult> $results
     */
    public function render(array $results): string
    {
        $lines = ['QA tooling present:'];

        $byCategory = [];
        foreach ($results as $result) {
            $byCategory[$result->tool->category][] = $result;
        }

        $present = 0;
        foreach ($byCategory as $category => $tools) {
            $lines[] = '  ' . $category;
            foreach ($tools as $result) {
                $mark = $result->present ? '✓' : '✗';
                $flag = $result->required && !$result->present ? ' (REQUIRED, missing)' : '';
                $evidence = $result->present ? ' [' . implode(', ', $result->evidence) . ']' : '';
                $lines[] = sprintf('    %s %s%s%s', $mark, $result->tool->label, $evidence, $flag);
                if ($result->present) {
                    ++$present;
                }
            }
        }

        $coveredCategories = count(array_filter(
            $byCategory,
            static fn (array $tools) => [] !== array_filter($tools, static fn (QaToolResult $r) => $r->present),
        ));
        $missingRequired = $this->missingRequired($results);
        $lines[] = sprintf(
            '  -> %d/%d tools detected, %d/%d categories covered%s',
            $present,
            count($results),
            $coveredCategories,
            count($byCategory),
            [] === $missingRequired ? '' : sprintf(', %d required one(s) missing', count($missingRequired)),
        );

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
