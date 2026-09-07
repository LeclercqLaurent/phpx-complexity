<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Report;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Report\ConsoleReporter;
use PhpxComplexity\Tests\Support\SampleAudit;

final class ConsoleReporterTest extends TestCase
{
    use SampleAudit;

    public function testHeaderStatesWhatWasAnalysed(): void
    {
        self::assertStringContainsString('3 methods / 3 files', $this->render());
    }

    public function testExceededValuesAreMarked(): void
    {
        $output = $this->render();

        // "18!" means the value crosses its threshold; a bare "1" does not.
        self::assertMatchesRegularExpression('/18!\s/', $output);
        self::assertStringContainsString('src/Foo.php::compute (l.12)', $output);
    }

    public function testViolationSummaryCountsMethodsAndBreachesSeparately(): void
    {
        $output = $this->render();

        self::assertStringContainsString('2/3 methods affected (3 violation(s)', $output);
        self::assertStringContainsString('Cognitive (cognitive > 15): 1', $output);
        self::assertStringContainsString('Parameters (params > 7): 1', $output);
    }

    public function testDivergenceSectionCanBeHidden(): void
    {
        self::assertStringContainsString('Divergence', $this->render());
        self::assertStringNotContainsString('Divergence', $this->render(showDivergence: false));
    }

    /**
     * Le rapport reste factuel : aucune note, aucun score.
     */
    public function testNoScoreIsEverPrinted(): void
    {
        self::assertStringNotContainsStringIgnoringCase('score', $this->render());
    }

    private function render(bool $showDivergence = true): string
    {
        return (new ConsoleReporter($this->lenses(), $this->config()))
            ->render($this->audit(), $showDivergence);
    }
}
