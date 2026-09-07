<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Report;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Report\DeltaReporter;
use PhpxComplexity\Tests\Support\SampleAudit;

final class DeltaReporterTest extends TestCase
{
    use SampleAudit;

    public function testEachCategoryIsReportedSeparately(): void
    {
        $output = $this->render();

        self::assertStringContainsString('New violations (2)', $output);
        self::assertStringContainsString('Worsened violations (1)', $output);
        self::assertStringContainsString('Resolved violations (1)', $output);
    }

    public function testValuesAreShownAsAFactualBeforeAfter(): void
    {
        $output = $this->render();

        self::assertMatchesRegularExpression('/cognitive\s+12 → 18\s+src\/Foo\.php::compute/', $output);
        // A method absent from the reference has no "before" value.
        self::assertMatchesRegularExpression('/cognitive\s+— → 20\s+src\/Neuf\.php::fresh/', $output);
    }

    public function testRegressionCountExcludesResolvedOnes(): void
    {
        self::assertStringContainsString('-> 3 regression(s)', $this->render());
    }

    public function testMovedThresholdIsSignalled(): void
    {
        self::assertStringContainsString('Threshold "entangle" changed', $this->render());
        self::assertStringContainsString('4 -> 3', $this->render());
    }

    public function testAppearedAndDisappearedGiveContext(): void
    {
        self::assertStringContainsString('Methods added: 1, removed: 1', $this->render());
    }

    private function render(): string
    {
        return (new DeltaReporter())->render($this->comparison());
    }
}
