<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Report;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Coverage\CoverageReport;
use PhpxComplexity\Report\CoverageReporter;
use PhpxComplexity\Tests\Support\SampleAudit;

final class CoverageReporterTest extends TestCase
{
    use SampleAudit;

    /**
     * The central honesty rule of the module: with no report, coverage is "not
     * measured". Showing 0% would be an unfounded verdict.
     */
    public function testAbsentReportIsNotMeasuredRatherThanZero(): void
    {
        $output = (new CoverageReporter())->render(CoverageReport::notFound(), $this->presence());

        self::assertStringContainsString('not measured', $output);
        self::assertStringNotContainsString('0 %', $output);
        self::assertStringNotContainsString('0%', $output);
        // Et l'outil dit comment l'obtenir.
        self::assertStringContainsString('--coverage-clover', $output);
    }

    public function testFoundReportShowsFiguresAndItsSource(): void
    {
        $output = $this->render();

        // Trailing zeros are trimmed: 82.5%, not 82.50%.
        self::assertStringContainsString('82.5% lines', $output);
        self::assertStringContainsString('(330/400)', $output);
        self::assertStringContainsString('74% methods', $output);
        self::assertStringContainsString('build/logs/clover.xml', $output);
        self::assertStringContainsString('clover', $output);
    }

    /**
     * Test presence is a floor, not coverage: the report has to say so
     * explicitly so it is not misread.
     */
    public function testPresenceIsLabelledAsNotBeingCoverage(): void
    {
        $output = $this->render();

        self::assertStringContainsString('this is NOT coverage', $output);
        self::assertStringContainsString('4 test classes, 21 test methods', $output);
        self::assertStringContainsString('8/10 source classes have a *Test class', $output);
        self::assertStringContainsString('Bar', $output);
    }

    private function render(): string
    {
        return (new CoverageReporter())->render($this->coverageFound(), $this->presence());
    }
}
