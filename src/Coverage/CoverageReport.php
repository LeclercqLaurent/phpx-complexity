<?php

declare(strict_types=1);

namespace PhpxComplexity\Coverage;

/**
 * Résultat d'ingestion d'un rapport de couverture. La couverture est une mesure
 * d'EXÉCUTION : l'outil ne la calcule pas, il rapporte ce qu'un rapport généré
 * par le projet contient. Absence de rapport ⇒ couverture « non mesurée »
 * (jamais 0 %, qui serait un verdict infondé).
 */
final class CoverageReport
{
    private function __construct(
        public readonly bool $found,
        public readonly ?string $format,
        public readonly ?string $source,
        public readonly ?float $linePercent,
        public readonly ?int $linesCovered,
        public readonly ?int $linesValid,
        public readonly ?float $methodPercent,
    ) {
    }

    public static function notFound(): self
    {
        return new self(false, null, null, null, null, null, null);
    }

    public static function found(
        string $format,
        string $source,
        ?float $linePercent,
        ?int $linesCovered,
        ?int $linesValid,
        ?float $methodPercent,
    ): self {
        return new self(true, $format, $source, $linePercent, $linesCovered, $linesValid, $methodPercent);
    }
}
