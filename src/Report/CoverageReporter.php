<?php

declare(strict_types=1);

namespace PhpxComplexity\Report;

use PhpxComplexity\Coverage\CoverageReport;
use PhpxComplexity\Coverage\TestPresence;

/**
 * The text report of the tests and coverage section, in two distinct and honest
 * blocks: ACTUAL coverage (read from a report, or "not measured") and test
 * PRESENCE (a static proxy, explicitly not coverage).
 */
final class CoverageReporter
{
    private const UNTESTED_SAMPLE = 10;

    public function render(CoverageReport $coverage, TestPresence $presence): string
    {
        return $this->coverageBlock($coverage) . "\n" . $this->presenceBlock($presence) . "\n";
    }

    private function coverageBlock(CoverageReport $coverage): string
    {
        if (!$coverage->found) {
            return "Code coverage: not measured (no clover/cobertura report found).\n"
                . '  Generate one with: phpunit --coverage-clover clover.xml (requires Xdebug or PCOV).';
        }

        $line = sprintf('Code coverage: %s%% lines', $this->num($coverage->linePercent));
        if (null !== $coverage->linesValid) {
            $line .= sprintf(' (%d/%d)', (int) $coverage->linesCovered, $coverage->linesValid);
        }
        if (null !== $coverage->methodPercent) {
            $line .= sprintf(', %s%% methods', $this->num($coverage->methodPercent));
        }
        $line .= sprintf(' [source: %s, %s]', $coverage->source, $coverage->format);

        return $line;
    }

    private function presenceBlock(TestPresence $presence): string
    {
        $lines = ['Test presence (static, this is NOT coverage):'];
        $lines[] = sprintf(
            '  %d test classes, %d test methods; %d/%d source classes have a *Test class',
            $presence->testClasses,
            $presence->testMethods,
            $presence->testedClasses(),
            $presence->sourceClasses,
        );

        if ([] !== $presence->untestedClasses) {
            $sample = array_slice($presence->untestedClasses, 0, self::UNTESTED_SAMPLE);
            $suffix = count($presence->untestedClasses) > self::UNTESTED_SAMPLE ? ', …' : '';
            $lines[] = '  Source classes with no *Test class: ' . implode(', ', $sample) . $suffix;
        }

        return implode("\n", $lines);
    }

    private function num(?float $value): string
    {
        if (null === $value) {
            return 'n/a';
        }

        return floor($value) === $value ? (string) (int) $value : number_format($value, 1);
    }
}
