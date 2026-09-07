<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Report;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Report\JsonReporter;
use PhpxComplexity\Tests\Support\SampleAudit;

/**
 * The JSON output is not a display: it is the CONTRACT consumed by CI and by the
 * baseline, which uses it as its snapshot format. A renamed key silently breaks
 * the ratchet, hence assertions on structure rather than on shape.
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
        // JSON re-encodes a round float as an integer; the baseline reader
        // accepte les deux (is_numeric puis cast), cf. SnapshotTest.
        self::assertSame(15, $lenses['cognitive']['threshold']);
        self::assertSame('', $lenses['live_peak']['reference'], 'in-house lens: no Sonar reference');
        self::assertNotSame('', $lenses['entangle']['description']);
    }

    /**
     * The five fields the baseline comparator really reads.
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
        self::assertSame($descending, $divergences, 'the strongest divergence comes first');
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
        self::assertSame(3, $baseline['regressions'], 'new + worsened, never the resolved ones');
        self::assertSame(1, $baseline['appeared']);
        self::assertSame(1, $baseline['disappeared']);
        self::assertNull($baseline['newViolations'][1]['before'], 'method absent from the reference');
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

        self::fail('method not found: ' . $name);
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
