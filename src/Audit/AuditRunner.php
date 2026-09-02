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
 * Assemble un audit complet à partir d'un chemin LOCAL, et rien d'autre : aucun
 * accès réseau, aucune notion de CLI ni de format de sortie.
 *
 * C'est le point d'entrée que réutilisera tout appelant amont — un wrapper qui
 * clonerait un dépôt, par exemple — sans jamais toucher au cœur d'analyse.
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
