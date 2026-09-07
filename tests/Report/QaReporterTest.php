<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Report;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Report\QaReporter;
use PhpxComplexity\Tests\Support\SampleAudit;

/**
 * Le module `--qa` constate une PRÉSENCE, il ne juge pas : les assertions
 * check that it reports facts and produces no grade.
 */
final class QaReporterTest extends TestCase
{
    use SampleAudit;

    public function testPresentToolsCarryTheirEvidence(): void
    {
        $output = $this->render();

        self::assertStringContainsString('PHPStan', $output);
        self::assertStringContainsString('composer:phpstan/phpstan', $output);
        self::assertStringContainsString('phpstan.neon', $output);
    }

    public function testToolsAreGroupedByCategory(): void
    {
        $output = $this->render();

        self::assertStringContainsString('Analyse statique', $output);
        self::assertStringContainsString('Refactoring auto', $output);
    }

    public function testCountsPresentToolsAndCoveredCategories(): void
    {
        self::assertStringContainsString('1/3 tools detected, 1/3 categories covered', $this->render());
    }

    public function testMissingRequiredToolsAreSignalled(): void
    {
        self::assertStringContainsString('1 required one(s) missing', $this->render());
        self::assertSame(['phpunit'], (new QaReporter())->missingRequired($this->qaResults()));
    }

    public function testAnAbsentButOptionalToolIsNotCountedAsMissingRequired(): void
    {
        self::assertNotContains('rector', (new QaReporter())->missingRequired($this->qaResults()));
    }

    public function testNoScoreIsEverPrinted(): void
    {
        self::assertStringNotContainsStringIgnoringCase('score', $this->render());
    }

    private function render(): string
    {
        return (new QaReporter())->render($this->qaResults());
    }
}
