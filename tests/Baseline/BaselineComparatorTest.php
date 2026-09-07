<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Baseline;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Baseline\BaselineComparator;
use PhpxComplexity\Baseline\Comparison;
use PhpxComplexity\Baseline\DeltaCategory;
use PhpxComplexity\Baseline\MethodDelta;
use PhpxComplexity\Baseline\Snapshot;
use PhpxComplexity\Config\Config;
use PhpxComplexity\Lens\CognitiveComplexityLens;

final class BaselineComparatorTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../fixtures/baseline';

    public function testMethodCrossingTheThresholdIsANewViolation(): void
    {
        $names = $this->namesOf($this->compare(), DeltaCategory::NewViolation);

        // "crossing" goes from 10 to 16, "fresh" is born already above.
        self::assertSame(['crossing', 'fresh'], $names);
    }

    public function testViolationGrowingFurtherIsWorsened(): void
    {
        $deltas = $this->compare()->of(DeltaCategory::Worsened);

        self::assertCount(1, $deltas);
        self::assertSame('worse', $deltas[0]->name);
        self::assertSame(18.0, $deltas[0]->before);
        self::assertSame(22.0, $deltas[0]->after);
    }

    public function testViolationFallingBackUnderTheThresholdIsResolved(): void
    {
        self::assertSame(['fixed'], $this->namesOf($this->compare(), DeltaCategory::Resolved));
    }

    /**
     * The heart of the ratchet: an inherited violation nobody has touched must
     * produce no delta at all, or the baseline would serve no purpose.
     */
    public function testUnchangedLegacyViolationProducesNoDelta(): void
    {
        $touched = array_map(
            static fn (MethodDelta $delta): string => $delta->name,
            $this->compare()->deltas,
        );

        self::assertNotContains('stable', $touched);
    }

    public function testAppearedAndDisappearedMethodsAreListed(): void
    {
        $comparison = $this->compare();

        self::assertSame(['clean', 'fresh'], $this->sortedNames($comparison->appeared));
        self::assertSame(['gone'], $this->sortedNames($comparison->disappeared));
    }

    /**
     * Every surviving method shifted by one line between the two snapshots: if
     * identity depended on the line, each would look removed then re-added and the
     * whole file would seem rewritten.
     */
    public function testLineShiftAloneDoesNotBreakTheIdentity(): void
    {
        $comparison = $this->compare();

        self::assertNotContains('stable', $this->sortedNames($comparison->appeared));
        self::assertNotContains('stable', $this->sortedNames($comparison->disappeared));
        self::assertCount(2, $comparison->appeared, 'seules fresh et clean sont neuves');
    }

    public function testRegressionCountIgnoresResolvedAndLegacy(): void
    {
        // 2 new + 1 worsened; "fixed" (resolved) and "stable" are excluded.
        self::assertSame(3, $this->compare()->regressionCount());
    }

    public function testThresholdMovedSinceTheSnapshotIsReported(): void
    {
        $comparison = $this->compare(20.0);

        self::assertSame(['cognitive' => ['from' => 15.0, 'to' => 20.0]], $comparison->thresholdChanges);
        // Classement fait sur le seuil COURANT, celui que le gate doit imposer :
        // at 20, "crossing" (16) and "fresh" (19) are no longer violations,
        // whereas "worse" (18 -> 22) has just crossed it.
        self::assertSame(['worse'], $this->namesOf($comparison, DeltaCategory::NewViolation));
    }

    private function compare(float $threshold = 15.0): Comparison
    {
        $config = Config::defaults()->withOverrides(['thresholds' => ['cognitive' => $threshold]]);
        $comparator = new BaselineComparator([new CognitiveComplexityLens($threshold)], $config);

        return $comparator->compare(
            Snapshot::fromFile(self::FIXTURES . '/reference.json'),
            Snapshot::fromFile(self::FIXTURES . '/current.json'),
            'reference.json',
        );
    }

    /**
     * @return list<string>
     */
    private function namesOf(Comparison $comparison, DeltaCategory $category): array
    {
        $names = array_map(
            static fn (MethodDelta $delta): string => $delta->name,
            $comparison->of($category),
        );
        sort($names);

        return $names;
    }

    /**
     * @param list<\PhpxComplexity\Baseline\MethodSnapshot> $methods
     *
     * @return list<string>
     */
    private function sortedNames(array $methods): array
    {
        $names = array_map(static fn ($method): string => $method->name, $methods);
        sort($names);

        return $names;
    }
}
