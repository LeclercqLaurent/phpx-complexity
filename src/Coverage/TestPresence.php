<?php

declare(strict_types=1);

namespace PhpxComplexity\Coverage;

/**
 * Faits de PRÉSENCE de tests, obtenus statiquement. À ne PAS confondre avec la
 * couverture : qu'une classe `FooTest` existe ne prouve pas que `Foo` est testée
 * utilement. C'est un plancher (« ces classes n'ont aucun fichier de test »),
 * pas une mesure de ce que les tests exécutent.
 */
final class TestPresence
{
    /**
     * @param list<string> $untestedClasses classes concrètes sans classe *Test correspondante
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
