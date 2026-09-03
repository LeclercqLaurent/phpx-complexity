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
 * HtmlReporterTest ; ici on vérifie que les modules actifs produisent bien leur
 * section, et qu'aucune n'apparaît quand le module n'a pas tourné.
 */
final class HtmlReporterSectionsTest extends TestCase
{
    use SampleAudit;

    public function testSectionsAreAbsentWhenTheModulesDidNotRun(): void
    {
        $html = $this->render();

        self::assertStringNotContainsString('Outils de QA', $html);
        self::assertStringNotContainsString('Baseline —', $html);
    }

    public function testQaSectionListsToolsAndFlagsMissingRequiredOnes(): void
    {
        $html = $this->render(withQa: true);

        self::assertStringContainsString('Outils de QA — 1/3 présents', $html);
        self::assertStringContainsString('PHPStan', $html);
        self::assertStringContainsString('manquant (requis)', $html);
        self::assertStringContainsString('Outils requis manquants : PHPUnit', $html);
    }

    public function testCoverageSectionKeepsTheTwoBlocksDistinct(): void
    {
        $html = $this->render(withCoverage: true);

        self::assertStringContainsString('Tests &amp; couverture', $html);
        // Bloc « couverture réelle ».
        self::assertStringContainsString('Couverture lignes', $html);
        // Le HTML rend « 82.50 % » là où la console rend « 82.5% » : formatages
        // distincts par rapport, assumés tant qu'aucun n'induit en erreur.
        self::assertStringContainsString('82.50 %', $html);
        // Bloc « présence de tests », distinct et annoncé comme tel.
        self::assertStringContainsString('Classes testées', $html);
        self::assertStringContainsString('8/10', $html);
        self::assertStringContainsString('ne garantit ni des tests, ni leur couverture', $html);
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

        // « aucun rapport » et non « 0 % », qui serait un verdict infondé.
        self::assertStringContainsString('aucun rapport', $html);
        self::assertStringNotContainsString('0 %', $html);
    }

    public function testBaselineSectionShowsEachCategoryAndTheRegressionCount(): void
    {
        $html = $this->render(comparison: true);

        self::assertStringContainsString('Baseline — écarts par rapport à baseline.json', $html);
        self::assertStringContainsString('3 régression(s)', $html);
        self::assertStringContainsString('nouvelle', $html);
        self::assertStringContainsString('aggravée', $html);
        self::assertStringContainsString('résolue', $html);
        self::assertStringContainsString('src/Neuf.php::fresh', $html);
    }

    public function testDocumentStaysSelfContainedWithEverySectionOn(): void
    {
        $html = $this->render(withQa: true, withCoverage: true, comparison: true);

        self::assertStringStartsWith('<!DOCTYPE html>', $html);
        self::assertStringNotContainsString('src=', $html);
        self::assertStringNotContainsString('href=', $html);
        self::assertStringNotContainsStringIgnoringCase('score', $html);
    }

    private function render(bool $withQa = false, bool $withCoverage = false, bool $comparison = false): string
    {
        return (new HtmlReporter($this->lenses(), $this->config()))->render(
            $this->audit($withQa, $withCoverage),
            $comparison ? $this->comparison() : null,
        );
    }
}
