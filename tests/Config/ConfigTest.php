<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Config;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Config\Config;

final class ConfigTest extends TestCase
{
    public function testHtmlPathDefaultsToNull(): void
    {
        self::assertNull(Config::defaults()->htmlPath);
    }

    public function testHtmlPathReadFromJsonSection(): void
    {
        $config = Config::defaults()->withOverrides(['html' => ['path' => 'build/report.html']]);

        self::assertSame('build/report.html', $config->htmlPath);
    }

    public function testHtmlPathUnchangedWhenSectionAbsentOrInvalid(): void
    {
        $base = Config::defaults()->withOverrides(['html' => ['path' => 'a.html']]);

        // Section absente : on conserve la valeur courante.
        self::assertSame('a.html', $base->withOverrides([])->htmlPath);
        // Type invalide : ignoré, valeur courante conservée.
        self::assertSame('a.html', $base->withOverrides(['html' => ['path' => 123]])->htmlPath);
    }

    public function testExclusionMatchesASegmentOfTheRelativePath(): void
    {
        $config = Config::defaults();

        self::assertTrue($config->isExcluded('vendor/autoload.php'), 'segment de premier niveau');
        self::assertTrue($config->isExcluded('app/vendor/autoload.php'), 'segment imbriqué');
        self::assertFalse($config->isExcluded('src/Vendorish/Foo.php'), 'pas un segment complet');
    }

    public function testExclusionIgnoresWhatSurroundsTheAuditedRoot(): void
    {
        // Le chemin absolu du projet (/var/www/…, /home/x/tests/…) ne doit jamais
        // entrer en jeu : seul compte ce qui est SOUS la racine auditée.
        $config = Config::defaults()->withOverrides(['exclude' => ['/var/', '/tests/']]);

        self::assertFalse($config->isExcluded('src/Foo.php'));
        self::assertTrue($config->isExcluded('tests/FooTest.php'));
    }
}
