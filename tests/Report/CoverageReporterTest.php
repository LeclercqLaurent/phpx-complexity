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
     * Règle d'honnêteté centrale du module : sans rapport, la couverture est
     * « non mesurée ». Afficher 0 % serait un verdict infondé.
     */
    public function testAbsentReportIsNotMeasuredRatherThanZero(): void
    {
        $output = (new CoverageReporter())->render(CoverageReport::notFound(), $this->presence());

        self::assertStringContainsString('non mesurée', $output);
        self::assertStringNotContainsString('0 %', $output);
        self::assertStringNotContainsString('0%', $output);
        // Et l'outil dit comment l'obtenir.
        self::assertStringContainsString('--coverage-clover', $output);
    }

    public function testFoundReportShowsFiguresAndItsSource(): void
    {
        $output = $this->render();

        // Les zéros de queue sont élagués : 82.5 %, pas 82.50 %.
        self::assertStringContainsString('82.5% lignes', $output);
        self::assertStringContainsString('(330/400)', $output);
        self::assertStringContainsString('74% méthodes', $output);
        self::assertStringContainsString('build/logs/clover.xml', $output);
        self::assertStringContainsString('clover', $output);
    }

    /**
     * La présence de tests est un plancher, pas de la couverture : le rapport
     * doit le dire explicitement pour ne pas être lu de travers.
     */
    public function testPresenceIsLabelledAsNotBeingCoverage(): void
    {
        $output = $this->render();

        self::assertStringContainsString("n'est PAS de la couverture", $output);
        self::assertStringContainsString('4 classes de test, 21 méthodes de test', $output);
        self::assertStringContainsString('8/10 classes source ont une classe *Test', $output);
        self::assertStringContainsString('Bar', $output);
    }

    private function render(): string
    {
        return (new CoverageReporter())->render($this->coverageFound(), $this->presence());
    }
}
