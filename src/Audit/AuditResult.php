<?php

declare(strict_types=1);

namespace PhpxComplexity\Audit;

use PhpxComplexity\Analyzer\MethodResult;
use PhpxComplexity\Coverage\CoverageReport;
use PhpxComplexity\Coverage\TestPresence;
use PhpxComplexity\Qa\QaToolResult;

/**
 * Faits assemblés d'un audit : mesures des lentilles, et si les modules
 * correspondants ont tourné, présence des outils de QA et couverture.
 *
 * Objet inerte, passé tel quel à tous les rapports : c'est ce contrat unique qui
 * leur évite de trimballer six paramètres chacun (et qui laisse la place aux
 * futurs deltas de baseline sans faire exploser les signatures).
 */
final class AuditResult
{
    /**
     * @param list<MethodResult> $results
     * @param list<string>       $parseErrors
     * @param list<QaToolResult> $qaResults
     */
    public function __construct(
        public readonly array $results,
        public readonly int $files,
        public readonly array $parseErrors,
        public readonly array $qaResults = [],
        public readonly ?CoverageReport $coverage = null,
        public readonly ?TestPresence $presence = null,
    ) {
    }
}
