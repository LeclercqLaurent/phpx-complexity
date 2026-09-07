<?php

declare(strict_types=1);

namespace PhpxComplexity\Audit;

use PhpxComplexity\Analyzer\MethodResult;
use PhpxComplexity\Coverage\CoverageReport;
use PhpxComplexity\Coverage\TestPresence;
use PhpxComplexity\Qa\QaToolResult;

/**
 * The assembled facts of an audit: lens measurements and, when the matching
 * modules ran, QA tool presence and coverage.
 *
 * An inert object, passed as-is to every report: this single contract is what
 * spares each of them six parameters (and what leaves room for the
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
