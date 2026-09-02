<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Cli;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Cli\Options;

/**
 * Verrouille le contrat de la ligne de commande : c'est lui que les prochaines
 * options (baseline, sous-commande) viendront étendre.
 */
final class OptionsTest extends TestCase
{
    public function testDefaults(): void
    {
        $options = new Options([]);

        self::assertNull($options->path);
        self::assertFalse($options->help);
        self::assertFalse($options->json);
        self::assertFalse($options->html);
        self::assertNull($options->htmlTarget);
        self::assertFalse($options->qa);
        self::assertFalse($options->coverage);
        self::assertFalse($options->failOnViolations);
        self::assertNull($options->configFile);
        self::assertNull($options->top);
        self::assertSame([], $options->exclude);
        // La divergence est le coeur de l'outil : affichée sauf refus explicite.
        self::assertTrue($options->showDivergence);
    }

    public function testFlagsAndPath(): void
    {
        $options = new Options(['--json', '--qa', '--coverage', '--fail-on-violations', '--no-divergence', 'src/']);

        self::assertTrue($options->json);
        self::assertTrue($options->qa);
        self::assertTrue($options->coverage);
        self::assertTrue($options->failOnViolations);
        self::assertFalse($options->showDivergence);
        self::assertSame('src/', $options->path);
    }

    public function testHelpAliases(): void
    {
        self::assertTrue((new Options(['-h']))->help);
        self::assertTrue((new Options(['--help']))->help);
    }

    public function testHtmlWithoutValueHasNoTarget(): void
    {
        $options = new Options(['--html']);

        self::assertTrue($options->html);
        self::assertNull($options->htmlTarget);
    }

    public function testHtmlWithValueCarriesTheTarget(): void
    {
        $options = new Options(['--html=build/rapport.html']);

        self::assertTrue($options->html);
        self::assertSame('build/rapport.html', $options->htmlTarget);
    }

    public function testValuedOptions(): void
    {
        $options = new Options(['--top=5', '--config=custom.json', '--exclude=/tests/', '--exclude=/fixtures/']);

        self::assertSame(5, $options->top);
        self::assertSame('custom.json', $options->configFile);
        self::assertSame(['/tests/', '/fixtures/'], $options->exclude);
    }

    public function testBaselineOptions(): void
    {
        $options = new Options(['--baseline=baseline.json', '--fail-on-new']);

        self::assertSame('baseline.json', $options->baselineFile);
        self::assertTrue($options->failOnNew);
    }

    public function testBaselineOptionsDefaultToOff(): void
    {
        $options = new Options([]);

        self::assertNull($options->baselineFile);
        self::assertFalse($options->failOnNew);
    }

    public function testUnknownFlagIsIgnoredAndNotTakenForAPath(): void
    {
        $options = new Options(['--inconnu', 'src/']);

        self::assertSame('src/', $options->path);
    }
}
