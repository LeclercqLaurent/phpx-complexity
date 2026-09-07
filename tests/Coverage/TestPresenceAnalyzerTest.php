<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Coverage;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Coverage\TestPresenceAnalyzer;

final class TestPresenceAnalyzerTest extends TestCase
{
    private const PROJECT_SRC = __DIR__ . '/../fixtures/coverage-project/src';

    public function testCountsSourceAndTestClasses(): void
    {
        $presence = (new TestPresenceAnalyzer())->analyze(self::PROJECT_SRC);

        self::assertSame(2, $presence->sourceClasses, 'Foo + Bar');
        self::assertSame(1, $presence->testClasses, 'FooTest');
        self::assertSame(2, $presence->testMethods, 'testA + testB');
    }

    public function testFlagsClassesWithoutCorrespondingTest(): void
    {
        $presence = (new TestPresenceAnalyzer())->analyze(self::PROJECT_SRC);

        self::assertContains('Bar', $presence->untestedClasses, 'Bar n\'a pas de BarTest');
        self::assertNotContains('Foo', $presence->untestedClasses, 'Foo a FooTest');
        self::assertSame(1, $presence->testedClasses());
    }

    /**
     * Regression: the analyser's internal exclusions applied to the absolute
     * path, so a project filed under /var/www surfaced no class at all.
     */
    public function testScanIsNotVoidedByAnAncestorDirectoryName(): void
    {
        $presence = (new TestPresenceAnalyzer())->analyze(__DIR__ . '/../fixtures/var/www/project/src');

        self::assertSame(1, $presence->sourceClasses, 'Foo');
        self::assertSame(['Foo'], $presence->untestedClasses);
    }
}
