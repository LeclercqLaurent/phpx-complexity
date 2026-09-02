<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Cli;

use PHPUnit\Framework\TestCase;

/**
 * Codes de sortie du mode cliquet, vérifiés en lançant réellement le binaire :
 * c'est le contrat que consomme une CI, et lui seul fait foi.
 */
final class BaselineGateTest extends TestCase
{
    private const PROJECT = __DIR__ . '/../fixtures/baseline-project';
    private const CONFIG = self::PROJECT . '/strict.json';

    /**
     * Le projet fictif dépasse le seuil dès le départ. Sans baseline, le gate
     * classique échoue — c'est précisément ce qui le rend inutilisable sur du
     * legacy, et la raison d'être du cliquet.
     */
    public function testFailOnViolationsRejectsInheritedViolations(): void
    {
        [$code] = $this->cli('--fail-on-violations');

        self::assertSame(1, $code);
    }

    public function testFailOnNewAcceptsTheSameInheritedViolations(): void
    {
        [$code, $output] = $this->cli('--fail-on-new', '--baseline=' . self::PROJECT . '/matching-baseline.json');

        self::assertSame(0, $code, $output);
        self::assertStringContainsString('0 régression(s)', $output);
    }

    public function testFailOnNewRejectsAViolationAbsentFromTheBaseline(): void
    {
        [$code, $output] = $this->cli('--fail-on-new', '--baseline=' . self::PROJECT . '/empty-baseline.json');

        self::assertSame(1, $code);
        self::assertStringContainsString('Nouvelles violations (1)', $output);
        self::assertStringContainsString('compute', $output);
    }

    public function testFailOnNewWithoutBaselineIsAUsageError(): void
    {
        [$code, $output] = $this->cli('--fail-on-new');

        self::assertSame(2, $code);
        self::assertStringContainsString('--baseline', $output);
    }

    public function testUnreadableBaselineIsReported(): void
    {
        [$code, $output] = $this->cli('--baseline=' . self::PROJECT . '/absent.json');

        self::assertSame(2, $code);
        self::assertStringContainsString('Baseline illisible', $output);
    }

    public function testMalformedBaselineIsReported(): void
    {
        [$code, $output] = $this->cli('--baseline=' . self::PROJECT . '/broken-baseline.json');

        self::assertSame(2, $code);
        self::assertStringContainsString('Baseline invalide', $output);
    }

    /**
     * @return array{0:int,1:string}
     */
    private function cli(string ...$args): array
    {
        $command = escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg(__DIR__ . '/../../bin/phpx-complexity')
            . ' ' . escapeshellarg(self::PROJECT)
            . ' ' . escapeshellarg('--config=' . self::CONFIG);
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }

        $output = [];
        $code = 0;
        exec($command . ' 2>&1', $output, $code);

        return [$code, implode("\n", $output)];
    }
}
