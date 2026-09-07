<?php

declare(strict_types=1);

namespace PhpxComplexity\Coverage;

/**
 * Faits de PRÉSENCE de tests, obtenus statiquement. À ne PAS confondre avec la
 * coverage: the existence of a `FooTest` class does not prove that `Foo` is
 * usefully tested. It is a floor ("these classes have no test file at all"),
 * not a measurement of what the tests execute.
 */
final class TestPresence
{
    /**
     * @param list<string> $untestedClasses concrete classes with no matching *Test class
     */
    public function __construct(
        public readonly int $sourceClasses,
        public readonly int $testClasses,
        public readonly int $testMethods,
        public readonly array $untestedClasses,
    ) {
    }

    public function testedClasses(): int
    {
        return max(0, $this->sourceClasses - count($this->untestedClasses));
    }
}
