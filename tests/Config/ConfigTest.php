<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Config;

use PhpxComplexity\Config\Config;
use PHPUnit\Framework\TestCase;

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
}
