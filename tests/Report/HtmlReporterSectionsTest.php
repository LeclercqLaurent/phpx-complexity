<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Report;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Audit\AuditResult;
use PhpxComplexity\Coverage\CoverageReport;
use PhpxComplexity\Report\HtmlReporter;
use PhpxComplexity\Tests\Support\SampleAudit;

/**
 * Sections optionnelles du rapport HTML. Le rendu de base est couvert par
 * HtmlReporterTest; here we check that active modules do produce their section,
 * and that none appears when the module did not run.
 */
final class HtmlReporterSectionsTest extends TestCase
{
    use SampleAudit;

    public function testSectionsAreAbsentWhenTheModulesDidNotRun(): void
    {
        $html = $this->render();

        self::assertStringNotContainsString('QA tooling', $html);
        self::assertStringNotContainsString('Baseline:', $html);
    }

    public function testQaSectionListsToolsAndFlagsMissingRequiredOnes(): void
    {
        $html = $this->render(withQa: true);

        self::assertStringContainsString('QA tooling: 1/3 present', $html);
        self::assertStringContainsString('PHPStan', $html);
        self::assertStringContainsString('missing (required)', $html);
        self::assertStringContainsString('Required tools missing: PHPUnit', $html);
    }

    public function testCoverageSectionKeepsTheTwoBlocksDistinct(): void
    {
        $html = $this->render(withCoverage: true);

        self::assertStringContainsString('Tests &amp; coverage', $html);
        // The "actual coverage" block.
        self::assertStringContainsString('Line coverage', $html);
        // The HTML renders "82.50 %" where the console renders "82.5%": distinct
        // formatting per report, accepted as long as neither misleads.
        self::assertStringContainsString('82.50 %', $html);
        // The "test presence" block, distinct and announced as such.
        self::assertStringContainsString('Tested classes', $html);
        self::assertStringContainsString('8/10', $html);
        self::assertStringContainsString('guarantees neither tests nor their coverage', $html);
    }

    public function testUnmeasuredCoverageIsNeverShownAsZero(): void
    {
        $audit = $this->audit(withCoverage: true);
        $html = (new HtmlReporter($this->lenses(), $this->config()))->render(
            new AuditResult(
                results: $audit->results,
                files: $audit->files,
                parseErrors: [],
                coverage: CoverageReport::notFound(),
                presence: $this->presence(),
            ),
        );

        // "no report" rather than "0 %", which would be an unfounded verdict.
        self::assertStringContainsString('no report', $html);
        self::assertStringNotContainsString('0 %', $html);
    }

    public function testBaselineSectionShowsEachCategoryAndTheRegressionCount(): void
    {
        $html = $this->render(comparison: true);

        self::assertStringContainsString('Baseline: deltas against baseline.json', $html);
        self::assertStringContainsString('3 regression(s)', $html);
        self::assertStringContainsString('new', $html);
        self::assertStringContainsString('worsened', $html);
        self::assertStringContainsString('resolved', $html);
        self::assertStringContainsString('src/Neuf.php::fresh', $html);
    }

    public function testDocumentStaysSelfContainedWithEverySectionOn(): void
    {
        $html = $this->render(withQa: true, withCoverage: true, comparison: true);

        self::assertStringStartsWith('<!DOCTYPE html>', $html);
        self::assertStringNotContainsString('src=', $html);
        self::assertStringNotContainsStringIgnoringCase('score', $html);
        // Les href du menu ne sortent jamais du document.
        preg_match_all('/href="([^"]*)"/', $html, $hrefs);
        foreach ($hrefs[1] as $href) {
            self::assertStringStartsWith('#', $href);
        }
    }

    public function testSectionsFollowTheReadingOrder(): void
    {
        $html = $this->render(withQa: true, withCoverage: true, comparison: true);

        // Context (baseline, tooling, tests) comes before the raw data, which
        // comes before the cross-analysis.
        $order = ['id="lenses"', 'id="baseline"', 'id="qa"', 'id="coverage"', 'id="method-list"', 'id="divergence"'];
        $positions = array_map(static fn (string $needle): int => (int) strpos($html, $needle), $order);

        $sorted = $positions;
        sort($sorted);
        self::assertSame($sorted, $positions, implode(' then ', $order));
    }

    public function testMenuOnlyLinksToRenderedSections(): void
    {
        $withoutModules = $this->render();

        self::assertStringContainsString('href="#method-list"', $withoutModules);
        self::assertStringContainsString('href="#divergence"', $withoutModules);
        self::assertStringNotContainsString('href="#qa"', $withoutModules, 'module did not run');
        self::assertStringNotContainsString('href="#baseline"', $withoutModules);

        $withModules = $this->render(withQa: true, withCoverage: true, comparison: true);
        self::assertStringContainsString('href="#qa"', $withModules);
        self::assertStringContainsString('href="#coverage"', $withModules);
        self::assertStringContainsString('href="#baseline"', $withModules);
    }

    /**
     * The filter opens on what needs action; the rest is one click away.
     */
    public function testMethodFilterDefaultsToBreachedThresholds(): void
    {
        $html = $this->render();

        self::assertMatchesRegularExpression(
            '/<option value="viol" selected>/',
            $html,
        );
        self::assertStringContainsString('value="ok"', $html);
        self::assertStringContainsString('value="all"', $html);
    }

    public function testDivergenceIsRenderedAsAGridOfLensPairs(): void
    {
        $html = $this->render();

        // A grid filled in on the client side, no more axis selectors.
        self::assertStringContainsString('<div class="pairs" id="pairs">', $html);
        self::assertStringNotContainsString('id="axisX"', $html);
        self::assertStringNotContainsString('id="axisY"', $html);
        // Cinq lentilles : dix couples attendus, construits par le script.
        self::assertStringContainsString('for(var j=i+1;j<L.length;j++)', $html);
    }

    private function render(bool $withQa = false, bool $withCoverage = false, bool $comparison = false): string
    {
        return (new HtmlReporter($this->lenses(), $this->config()))->render(
            $this->audit($withQa, $withCoverage),
            $comparison ? $this->comparison() : null,
        );
    }
}
