<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Cli;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Vcs\Checkout;

/**
 * Bout en bout de la sous-commande fetch, avec un faux « git » en tête de PATH :
 * on vérifie le contrat CLI sans jamais sortir sur le réseau.
 */
final class FetchCommandTest extends TestCase
{
    private const STUB_BIN = __DIR__ . '/../fixtures/bin';

    private string $temporary;

    protected function setUp(): void
    {
        $this->temporary = __DIR__ . '/../../var/fetch-' . bin2hex(random_bytes(4));
        mkdir($this->temporary, 0o755, true);
    }

    protected function tearDown(): void
    {
        (new Checkout($this->temporary, $this->temporary))->remove();
    }

    public function testFetchAnalysesTheClonedRepository(): void
    {
        [$code, $output] = $this->cli('fetch', 'https://example.org/projet.git');

        self::assertSame(0, $code, $output);
        self::assertStringContainsString('1 méthodes / 1 fichiers', $output);
        self::assertStringContainsString('src/Cloned.php::run', $output);
    }

    public function testTemporaryCopyIsRemovedAfterwards(): void
    {
        $this->cli('fetch', 'https://example.org/projet.git');

        self::assertSame([], $this->leftovers(), 'aucune copie résiduelle');
    }

    public function testKeepPreservesTheCopyAndSaysWhere(): void
    {
        [$code, $output] = $this->cli('fetch', 'https://example.org/projet.git', '--keep');

        self::assertSame(0, $code, $output);
        self::assertStringContainsString('Copie conservée', $output);
        self::assertCount(1, $this->leftovers());
    }

    public function testCloneFailureIsReportedAndCleanedUp(): void
    {
        [$code, $output] = $this->cli('fetch', 'https://example.org/echec.git');

        self::assertSame(2, $code);
        self::assertStringContainsString('Clonage impossible', $output);
        self::assertSame([], $this->leftovers());
    }

    public function testRejectedUrlNeverReachesGit(): void
    {
        [$code, $output] = $this->cli('fetch', 'ext::sh -c whoami');

        self::assertSame(2, $code);
        self::assertStringContainsString('URL de dépôt non supportée', $output);
        self::assertSame([], $this->leftovers(), 'aucun temporaire créé avant validation');
    }

    public function testFetchWithoutUrlIsAUsageError(): void
    {
        [$code, $output] = $this->cli('fetch');

        self::assertSame(2, $code);
        self::assertStringContainsString('attend une URL', $output);
    }

    /**
     * @return array{0:int,1:string}
     */
    private function cli(string ...$args): array
    {
        $command = 'TMPDIR=' . escapeshellarg($this->temporary)
            . ' PATH=' . escapeshellarg(self::STUB_BIN . ':' . (string) getenv('PATH'))
            . ' ' . escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg(__DIR__ . '/../../bin/phpx-complexity');
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }

        $output = [];
        $code = 0;
        exec($command . ' 2>&1', $output, $code);

        return [$code, implode("\n", $output)];
    }

    /**
     * @return list<string>
     */
    private function leftovers(): array
    {
        return array_values(array_diff((array) scandir($this->temporary), ['.', '..']));
    }
}
