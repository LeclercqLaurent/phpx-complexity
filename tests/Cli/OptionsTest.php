<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Cli;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Cli\Command;
use PhpxComplexity\Cli\Options;

/**
 * Verrouille le contrat de la ligne de commande : c'est lui que les prochaines
 * options (baseline, subcommand) will extend.
 */
final class OptionsTest extends TestCase
{
    public function testDefaults(): void
    {
        $options = new Options([]);

        self::assertNull($options->target);
        self::assertSame(Command::Audit, $options->command);
        self::assertFalse($options->keep);
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
        // Divergence is the heart of the tool: shown unless explicitly refused.
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
        self::assertSame('src/', $options->target);
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

    /**
     * A misspelled option must be reported, never absorbed: otherwise "--jsno"
     * would render a console report with exit code 0.
     */
    public function testUnknownFlagIsRecordedAndNotTakenForAPath(): void
    {
        $options = new Options(['--jsno', 'src/']);

        self::assertSame(['--jsno'], $options->unknown);
        self::assertSame('src/', $options->target);
        self::assertFalse($options->json);
    }

    public function testKnownFlagsLeaveNothingUnknown(): void
    {
        self::assertSame([], (new Options(['--json', '--qa', 'src/']))->unknown);
    }

    public function testFetchVerbConsumesTheFirstPositional(): void
    {
        $options = new Options(['fetch', 'https://example.org/depot.git', '--keep']);

        self::assertSame(Command::Fetch, $options->command);
        self::assertSame('https://example.org/depot.git', $options->target);
        self::assertTrue($options->keep);
    }

    public function testAuditVerbIsOptional(): void
    {
        // Both forms must designate the same target.
        self::assertSame('src/', (new Options(['src/']))->target);
        self::assertSame('src/', (new Options(['audit', 'src/']))->target);
    }
}
