<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Support;

use PhpxComplexity\Analyzer\MethodResult;
use PhpxComplexity\Audit\AuditResult;
use PhpxComplexity\Baseline\Comparison;
use PhpxComplexity\Baseline\DeltaCategory;
use PhpxComplexity\Baseline\MethodDelta;
use PhpxComplexity\Baseline\MethodSnapshot;
use PhpxComplexity\Config\Config;
use PhpxComplexity\Coverage\CoverageReport;
use PhpxComplexity\Coverage\TestPresence;
use PhpxComplexity\Lens\Lens;
use PhpxComplexity\Lens\LensRegistry;
use PhpxComplexity\Qa\QaTool;
use PhpxComplexity\Qa\QaToolResult;

/**
 * Jeu d'essai partagé par les tests de rapport : trois méthodes couvrant une
 * méthode en dépassement sur deux axes, une en dépassement sur la signature, et
 * une saine. Aux seuils par défaut : 3 dépassements sur 2 méthodes.
 */
trait SampleAudit
{
    protected function config(): Config
    {
        return Config::defaults();
    }

    /**
     * @return list<Lens>
     */
    protected function lenses(): array
    {
        return LensRegistry::defaults($this->config());
    }

    protected function audit(bool $withQa = false, bool $withCoverage = false): AuditResult
    {
        return new AuditResult(
            results: $this->methods(),
            files: 3,
            parseErrors: [],
            qaResults: $withQa ? $this->qaResults() : [],
            coverage: $withCoverage ? $this->coverageFound() : null,
            presence: $withCoverage ? $this->presence() : null,
        );
    }

    /**
     * @return list<MethodResult>
     */
    protected function methods(): array
    {
        return [
            new MethodResult(
                file: 'src/Foo.php',
                name: 'compute',
                line: 12,
                metrics: ['cognitive' => 18.0, 'params' => 1.0, 'returns' => 1.0, 'live_peak' => 9.0, 'entangle' => 2.5],
                percentile: ['cognitive' => 1.0, 'params' => 0.0, 'returns' => 0.2, 'live_peak' => 0.9, 'entangle' => 0.6],
                divergence: 1.0,
            ),
            new MethodResult(
                file: 'src/Bar.php',
                name: 'restore',
                line: 4,
                metrics: ['cognitive' => 0.0, 'params' => 8.0, 'returns' => 1.0, 'live_peak' => 8.0, 'entangle' => 0.0],
                percentile: ['cognitive' => 0.0, 'params' => 1.0, 'returns' => 0.2, 'live_peak' => 0.8, 'entangle' => 0.0],
                divergence: 1.0,
            ),
            new MethodResult(
                file: 'src/Baz.php',
                name: 'simple',
                line: 7,
                metrics: ['cognitive' => 1.0, 'params' => 1.0, 'returns' => 1.0, 'live_peak' => 2.0, 'entangle' => 0.0],
                percentile: ['cognitive' => 0.5, 'params' => 0.5, 'returns' => 0.5, 'live_peak' => 0.4, 'entangle' => 0.0],
                divergence: 0.5,
            ),
        ];
    }

    /**
     * @return list<QaToolResult>
     */
    protected function qaResults(): array
    {
        return [
            new QaToolResult(
                new QaTool('phpstan', 'PHPStan', 'Analyse statique', ['phpstan/phpstan'], ['phpstan.neon']),
                present: true,
                evidence: ['composer:phpstan/phpstan', 'phpstan.neon'],
                required: true,
            ),
            new QaToolResult(
                new QaTool('phpunit', 'PHPUnit', 'Tests', ['phpunit/phpunit'], ['phpunit.xml.dist']),
                present: false,
                evidence: [],
                required: true,
            ),
            new QaToolResult(
                new QaTool('rector', 'Rector', 'Refactoring auto', ['rector/rector'], ['rector.php']),
                present: false,
                evidence: [],
                required: false,
            ),
        ];
    }

    protected function coverageFound(): CoverageReport
    {
        return CoverageReport::found('clover', 'build/logs/clover.xml', 82.5, 330, 400, 74.0);
    }

    protected function presence(): TestPresence
    {
        return new TestPresence(sourceClasses: 10, testClasses: 4, testMethods: 21, untestedClasses: ['Bar', 'Baz']);
    }

    protected function comparison(): Comparison
    {
        return new Comparison(
            source: 'baseline.json',
            deltas: [
                new MethodDelta(DeltaCategory::NewViolation, 'src/Foo.php', 'compute', 'cognitive', 12.0, 18.0),
                new MethodDelta(DeltaCategory::Worsened, 'src/Foo.php', 'compute', 'live_peak', 9.0, 11.0),
                new MethodDelta(DeltaCategory::Resolved, 'src/Bar.php', 'restore', 'returns', 5.0, 2.0),
                new MethodDelta(DeltaCategory::NewViolation, 'src/Neuf.php', 'fresh', 'cognitive', null, 20.0),
            ],
            appeared: [new MethodSnapshot('src/Neuf.php::fresh', 'src/Neuf.php', 'fresh', 3, ['cognitive' => 20.0])],
            disappeared: [new MethodSnapshot('src/Old.php::gone', 'src/Old.php', 'gone', 9, ['cognitive' => 30.0])],
            thresholdChanges: ['entangle' => ['from' => 4.0, 'to' => 3.0]],
        );
    }
}
