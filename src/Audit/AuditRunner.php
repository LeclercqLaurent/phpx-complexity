<?php

declare(strict_types=1);

namespace PhpxComplexity\Audit;

use PhpxComplexity\Analyzer\ProjectAnalyzer;
use PhpxComplexity\Config\Config;
use PhpxComplexity\Coverage\CoverageReportReader;
use PhpxComplexity\Coverage\TestPresenceAnalyzer;
use PhpxComplexity\Lens\Lens;
use PhpxComplexity\Qa\QaPresenceChecker;
use PhpxComplexity\Qa\QaToolRegistry;
use PhpxComplexity\Qa\QaToolResult;

/**
 * Assembles a complete audit from a LOCAL path and nothing else: no network
 * access, no notion of a CLI or of an output format.
 *
 * This is the entry point any upstream caller reuses, a wrapper cloning a
 * repository for instance, without ever touching the analysis core.
 */
final class AuditRunner
{
    /**
     * @param list<Lens> $lenses
     */
    public function __construct(
        private readonly array $lenses,
        private readonly Config $config,
    ) {
    }

    public function run(string $path, bool $withQa = false, bool $withCoverage = false): AuditResult
    {
        $analysis = (new ProjectAnalyzer($this->lenses, $this->config))->analyze($path);

        return new AuditResult(
            results: $analysis['results'],
            files: $analysis['files'],
            parseErrors: $analysis['parseErrors'],
            qaResults: $withQa ? $this->checkQa($path) : [],
            coverage: $withCoverage ? (new CoverageReportReader())->read($path, $this->config->coveragePath) : null,
            presence: $withCoverage ? (new TestPresenceAnalyzer())->analyze($path) : null,
        );
    }

    /**
     * @return list<QaToolResult>
     */
    private function checkQa(string $path): array
    {
        return (new QaPresenceChecker(QaToolRegistry::defaults(), $this->config->qaRequired))->check($path);
    }
}
