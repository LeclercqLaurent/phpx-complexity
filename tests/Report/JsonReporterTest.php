<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Report;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Report\JsonReporter;
use PhpxComplexity\Tests\Support\SampleAudit;

/**
 * La sortie JSON n'est pas un affichage : c'est le CONTRAT consommé par la CI et
 * par la baseline, qui s'en sert de format d'instantané. Une clé renommée casse
 * silencieusement le cliquet — d'où des assertions de structure, pas de forme.
 */
final class JsonReporterTest extends TestCase
{
    use SampleAudit;

    public function testSummaryCountsViolationsAndMethods(): void
    {
        $payload = $this->render();

        self::assertSame('phpx-complexity', $payload['tool']);
        self::assertSame(3, $payload['summary']['files']);
        self::assertSame(3, $payload['summary']['methods']);
        self::assertSame(2, $payload['summary']['methodsInViolation'], 'compute et restore');
        self::assertSame(3, $payload['summary']['totalViolations'], 'cognitive + live_peak + params');
    }

    public function testEachLensIsDocumentedWithItsThreshold(): void
    {
        $lenses = $this->render()['lenses'];

        self::assertSame(['cognitive', 'params', 'returns', 'live_peak', 'entangle'], array_keys($lenses));
        self::assertSame('S3776', $lenses['cognitive']['reference']);
        // JSON réencode un flottant rond en entier ; le lecteur de baseline
        // accepte les deux (is_numeric puis cast), cf. SnapshotTest.
        self::assertSame(15, $lenses['cognitive']['threshold']);
        self::assertSame('', $lenses['live_peak']['reference'], 'lentille maison : pas de référence Sonar');
        self::assertNotSame('', $lenses['entangle']['description']);
    }

    /**
     * Les cinq champs que le comparateur de baseline lit réellement.
     */
    public function testEachMethodCarriesTheFieldsTheBaselineNeeds(): void
    {
        $method = $this->methodNamed('compute');

        self::assertSame('src/Foo.php', $method['file']);
        self::assertSame(12, $method['line']);
        self::assertSame(18, $method['metrics']['cognitive']);
        self::assertSame(['cognitive', 'live_peak'], $method['violations']);
        self::assertSame(1, $method['divergence']);
        self::assertArrayHasKey('percentile', $method);
    }

    public function testMethodsAreOrderedByDivergence(): void
    {
        $divergences = array_map(
            static fn (array $m): float => $m['divergence'],
            $this->render()['methods'],
        );

        $descending = $divergences;
        rsort($descending);
        self::assertSame($descending, $divergences, 'la divergence la plus forte en tête');
    }

    public function testQaAndCoverageBlocksAppearOnlyWhenTheModulesRan(): void
    {
        $without = $this->render();
        self::assertArrayNotHasKey('qa', $without);
        self::assertArrayNotHasKey('coverage', $without);

        $with = $this->render(withQa: true, withCoverage: true);
        self::assertSame(['phpunit'], $with['qa']['missingRequired']);
        self::assertSame(1, $with['qa']['toolsPresent']);
        self::assertSame(3, $with['qa']['toolsTotal']);
        self::assertTrue($with['coverage']['report']['measured']);
        self::assertSame(82.5, $with['coverage']['report']['linePercent']);
        self::assertSame(['Bar', 'Baz'], $with['coverage']['testPresence']['untestedClasses']);
    }

    public function testBaselineBlockCarriesTheDeltasAndRegressionCount(): void
    {
        $baseline = $this->render(comparison: true)['baseline'];

        self::assertSame('baseline.json', $baseline['source']);
        self::assertCount(2, $baseline['newViolations']);
        self::assertCount(1, $baseline['worsened']);
        self::assertCount(1, $baseline['resolved']);
        self::assertSame(3, $baseline['regressions'], 'nouvelles + aggravées, jamais les résolues');
        self::assertSame(1, $baseline['appeared']);
        self::assertSame(1, $baseline['disappeared']);
        self::assertNull($baseline['newViolations'][1]['before'], 'méthode absente de la référence');
        self::assertSame(['entangle' => ['from' => 4, 'to' => 3]], $baseline['thresholdChanges']);
    }

    /**
     * @return array<string,mixed>
     */
    private function methodNamed(string $name): array
    {
        foreach ($this->render()['methods'] as $method) {
            if ($method['name'] === $name) {
                return $method;
            }
        }

        self::fail('méthode absente : ' . $name);
    }

    /**
     * @return array<string,mixed>
     */
    private function render(bool $withQa = false, bool $withCoverage = false, bool $comparison = false): array
    {
        $json = (new JsonReporter($this->lenses(), $this->config()))->render(
            $this->audit($withQa, $withCoverage),
            $comparison ? $this->comparison() : null,
        );

        /** @var array<string,mixed> $decoded */
        $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
