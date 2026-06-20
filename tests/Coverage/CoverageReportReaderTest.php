<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Coverage;

use PhpxComplexity\Coverage\CoverageReportReader;
use PHPUnit\Framework\TestCase;

final class CoverageReportReaderTest extends TestCase
{
    private const PROJECT = __DIR__ . '/../fixtures/coverage-project';
    private const NO_REPORT = __DIR__ . '/../fixtures/sample-project';

    public function testReadsCloverReport(): void
    {
        $report = (new CoverageReportReader())->read(self::PROJECT, null);

        self::assertTrue($report->found);
        self::assertSame('clover', $report->format);
        self::assertSame(80.0, $report->linePercent);
        self::assertSame(90.0, $report->methodPercent);
        self::assertSame(80, $report->linesCovered);
        self::assertSame(100, $report->linesValid);
    }

    public function testReadsCoberturaViaConfiguredPath(): void
    {
        $report = (new CoverageReportReader())->read(self::PROJECT, self::PROJECT . '/cobertura-coverage.xml');

        self::assertSame('cobertura', $report->format);
        self::assertSame(75.0, $report->linePercent);
        self::assertSame(150, $report->linesCovered);
        self::assertSame(200, $report->linesValid);
    }

    public function testAbsentReportIsNotMeasured(): void
    {
        $report = (new CoverageReportReader())->read(self::NO_REPORT, null);

        self::assertFalse($report->found);
        self::assertNull($report->linePercent);
    }
}
