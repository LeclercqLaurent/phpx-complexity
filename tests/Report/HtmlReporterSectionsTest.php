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

        // Le contexte (baseline, outillage, tests) précède les données brutes,
        // qui précèdent l'analyse croisée.
        $order = ['id="lentilles"', 'id="baseline"', 'id="qa"', 'id="couverture"', 'id="methodes"', 'id="divergence"'];
        $positions = array_map(static fn (string $needle): int => (int) strpos($html, $needle), $order);

        $sorted = $positions;
        sort($sorted);
        self::assertSame($sorted, $positions, implode(' puis ', $order));
    }

    public function testMenuOnlyLinksToRenderedSections(): void
    {
        $withoutModules = $this->render();

        self::assertStringContainsString('href="#methodes"', $withoutModules);
        self::assertStringContainsString('href="#divergence"', $withoutModules);
        self::assertStringNotContainsString('href="#qa"', $withoutModules, 'module non lancé');
        self::assertStringNotContainsString('href="#baseline"', $withoutModules);

        $withModules = $this->render(withQa: true, withCoverage: true, comparison: true);
        self::assertStringContainsString('href="#qa"', $withModules);
        self::assertStringContainsString('href="#couverture"', $withModules);
        self::assertStringContainsString('href="#baseline"', $withModules);
    }

    /**
     * Le filtre s'ouvre sur ce qui demande une action ; le reste est à un clic.
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

        // Une grille à remplir côté client, plus de sélecteurs d'axes.
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
