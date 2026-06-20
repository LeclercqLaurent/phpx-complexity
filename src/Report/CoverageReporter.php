<?php

declare(strict_types=1);

namespace PhpxComplexity\Report;

use PhpxComplexity\Coverage\CoverageReport;
use PhpxComplexity\Coverage\TestPresence;

/**
 * Rapport texte de la section tests/couverture, en deux blocs distincts et
 * honnêtes : la couverture RÉELLE (lue d'un rapport, ou « non mesurée ») et la
 * PRÉSENCE de tests (proxy statique, explicitement pas de la couverture).
 */
final class CoverageReporter
{
    private const UNTESTED_SAMPLE = 10;

    public function render(CoverageReport $coverage, TestPresence $presence): string
    {
        return $this->coverageBlock($coverage) . "\n" . $this->presenceBlock($presence) . "\n";
    }

    private function coverageBlock(CoverageReport $coverage): string
    {
        if (!$coverage->found) {
            return "Couverture de code : non mesurée (aucun rapport clover/cobertura trouvé).\n"
                . '  Générer : phpunit --coverage-clover clover.xml (nécessite Xdebug ou PCOV).';
        }

        $line = sprintf('Couverture de code : %s%% lignes', $this->num($coverage->linePercent));
        if (null !== $coverage->linesValid) {
            $line .= sprintf(' (%d/%d)', (int) $coverage->linesCovered, $coverage->linesValid);
        }
        if (null !== $coverage->methodPercent) {
            $line .= sprintf(', %s%% méthodes', $this->num($coverage->methodPercent));
        }
        $line .= sprintf(' [source : %s, %s]', $coverage->source, $coverage->format);

        return $line;
    }

    private function presenceBlock(TestPresence $presence): string
    {
        $lines = ['Présence de tests (statique — n\'est PAS de la couverture) :'];
        $lines[] = sprintf(
            '  %d classes de test, %d méthodes de test ; %d/%d classes source ont une classe *Test',
            $presence->testClasses,
            $presence->testMethods,
            $presence->testedClasses(),
            $presence->sourceClasses,
        );

        if ([] !== $presence->untestedClasses) {
            $sample = array_slice($presence->untestedClasses, 0, self::UNTESTED_SAMPLE);
            $suffix = count($presence->untestedClasses) > self::UNTESTED_SAMPLE ? ', …' : '';
            $lines[] = '  Classes source sans classe *Test : ' . implode(', ', $sample) . $suffix;
        }

        return implode("\n", $lines);
    }

    private function num(?float $value): string
    {
        if (null === $value) {
            return 'n/a';
        }

        return floor($value) === $value ? (string) (int) $value : number_format($value, 1);
    }
}
