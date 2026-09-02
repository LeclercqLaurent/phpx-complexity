<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Analyzer;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Analyzer\ProjectAnalyzer;
use PhpxComplexity\Config\Config;
use PhpxComplexity\Lens\LensRegistry;

final class ProjectAnalyzerTest extends TestCase
{
    /**
     * Régression : les fragments d'exclusion étaient comparés au chemin ABSOLU.
     * Un projet installé sous /var/www/ était donc entièrement écarté par le
     * fragment « /var/ » livré par défaut, et l'audit rendait zéro fichier.
     */
    public function testExclusionsAreMatchedRelativeToTheAuditedRoot(): void
    {
        $analysis = $this->analyze(__DIR__ . '/../fixtures/var/www/project');

        self::assertSame(1, $analysis['files'], 'src/Foo.php analysé, vendor/ écarté');
        self::assertSame(['compute'], array_map(
            static fn ($result) => $result->name,
            $analysis['results'],
        ));
    }

    public function testVendorIsStillExcludedBelowTheRoot(): void
    {
        $analysis = $this->analyze(__DIR__ . '/../fixtures/var/www/project');

        self::assertSame([], array_filter(
            $analysis['results'],
            static fn ($result) => str_contains($result->file, 'vendor/'),
        ));
    }

    /**
     * @return array{results: list<\PhpxComplexity\Analyzer\MethodResult>, parseErrors: list<string>, files: int}
     */
    private function analyze(string $path): array
    {
        $config = Config::defaults();

        return (new ProjectAnalyzer(LensRegistry::defaults($config), $config))->analyze($path);
    }
}
