<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Audit;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Audit\AuditResult;
use PhpxComplexity\Audit\AuditRunner;
use PhpxComplexity\Config\Config;
use PhpxComplexity\Lens\LensRegistry;

/**
 * Le point d'entrée que réutilise tout appelant amont : il n'accepte qu'un
 * chemin local et n'active les modules optionnels que si on les demande.
 */
final class AuditRunnerTest extends TestCase
{
    private const PROJECT = __DIR__ . '/../fixtures/coverage-project';

    public function testMeasuresWithoutTheOptionalModulesByDefault(): void
    {
        $result = $this->runOn(self::PROJECT . '/src');

        self::assertSame(2, $result->files, 'Foo.php + Bar.php');
        self::assertNotSame([], $result->results);
        self::assertSame([], $result->parseErrors);
        self::assertSame([], $result->qaResults, 'module QA non demandé');
        self::assertNull($result->coverage);
        self::assertNull($result->presence);
    }

    public function testOptionalModulesRunOnlyWhenAskedFor(): void
    {
        $result = $this->runOn(self::PROJECT, withQa: true, withCoverage: true);

        self::assertNotSame([], $result->qaResults);
        self::assertNotNull($result->coverage);
        self::assertNotNull($result->presence);
    }

    public function testEveryMeasuredMethodCarriesEveryLens(): void
    {
        foreach ($this->runOn(self::PROJECT . '/src')->results as $method) {
            self::assertSame(
                ['cognitive', 'params', 'returns', 'live_peak', 'entangle'],
                array_keys($method->metrics),
                $method->name,
            );
        }
    }

    private function runOn(string $path, bool $withQa = false, bool $withCoverage = false): AuditResult
    {
        $config = Config::defaults();

        return (new AuditRunner(LensRegistry::defaults($config), $config))->run($path, $withQa, $withCoverage);
    }
}
