<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Cli;

use PHPUnit\Framework\TestCase;

/**
 * The exit codes of ratchet mode, checked by actually running the binary:
 * c'est le contrat que consomme une CI, et lui seul fait foi.
 */
final class BaselineGateTest extends TestCase
{
    private const PROJECT = __DIR__ . '/../fixtures/baseline-project';
    private const CONFIG = self::PROJECT . '/strict.json';

    /**
     * The fictional project crosses the threshold from the start. With no
     * baseline the classic gate fails, which is exactly what makes it unusable on
     * legacy code, and the reason the ratchet exists.
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
        self::assertStringContainsString('0 regression(s)', $output);
    }

    public function testFailOnNewRejectsAViolationAbsentFromTheBaseline(): void
    {
        [$code, $output] = $this->cli('--fail-on-new', '--baseline=' . self::PROJECT . '/empty-baseline.json');

        self::assertSame(1, $code);
        self::assertStringContainsString('New violations (1)', $output);
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
        self::assertStringContainsString('Invalid baseline', $output);
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
