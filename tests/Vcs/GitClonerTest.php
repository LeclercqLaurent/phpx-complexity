<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Vcs;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Vcs\Checkout;
use PhpxComplexity\Vcs\Exception\GitException;
use PhpxComplexity\Vcs\GitCloner;
use PhpxComplexity\Vcs\RepositoryUrl;

/**
 * Cloning is exercised with a fake "git": the tests stay offline and
 * deterministic while still checking the real orchestration (temp directory,
 * options
 * de durcissement, nettoyage).
 */
final class GitClonerTest extends TestCase
{
    private const STUB = __DIR__ . '/../fixtures/bin/git';

    private string $base;

    protected function setUp(): void
    {
        $this->base = __DIR__ . '/../../var/checkouts-' . bin2hex(random_bytes(4));
        mkdir($this->base, 0o755, true);
    }

    protected function tearDown(): void
    {
        (new Checkout($this->base, $this->base))->remove();
    }

    public function testClonedRepositoryIsAnalysableLocalPath(): void
    {
        $checkout = $this->cloner()->fetch($this->url('https://example.org/projet.git'));

        self::assertDirectoryExists($checkout->path);
        self::assertFileExists($checkout->path . '/src/Cloned.php');
        self::assertStringStartsWith($this->base, $checkout->root);
    }

    /**
     * The hardening has to be effective, not merely documented.
     */
    public function testCloneCommandIsHardened(): void
    {
        $checkout = $this->cloner()->fetch($this->url('https://example.org/projet.git'));
        $arguments = explode("\n", (string) file_get_contents($checkout->root . '/args.txt'));

        self::assertContains('--depth=1', $arguments, 'clone superficiel');
        self::assertContains('protocol.ext.allow=never', $arguments, 'transport ext interdit');
        self::assertContains('--', $arguments, 'separator before the URL');
        self::assertContains('core.hooksPath=' . $checkout->root . '/hooks', $arguments, 'hooks neutralised');
    }

    public function testHooksDirectoryIsCreatedEmpty(): void
    {
        $checkout = $this->cloner()->fetch($this->url('https://example.org/projet.git'));

        self::assertDirectoryExists($checkout->root . '/hooks');
        self::assertSame([], array_values(array_diff(
            (array) scandir($checkout->root . '/hooks'),
            ['.', '..'],
        )));
    }

    public function testFailedCloneLeavesNothingBehind(): void
    {
        $before = $this->entries();

        try {
            $this->cloner()->fetch($this->url('https://example.org/echec.git'));
            self::fail('a failed clone must raise an exception');
        } catch (GitException $e) {
            self::assertStringContainsString('Clonage impossible', $e->getMessage());
            self::assertStringContainsString('not found', $e->getMessage(), 'the reason given by git is reported');
        }

        self::assertSame($before, $this->entries(), 'no leftover temporary directory');
    }

    public function testMissingGitBinaryIsReportedClearly(): void
    {
        $this->expectException(GitException::class);
        $this->expectExceptionMessage('not found');

        (new GitCloner(__DIR__ . '/absent-git', $this->base))->fetch($this->url('https://example.org/projet.git'));
    }

    public function testRemoveDeletesNestedContent(): void
    {
        $checkout = $this->cloner()->fetch($this->url('https://example.org/projet.git'));

        $checkout->remove();

        self::assertDirectoryDoesNotExist($checkout->root);
    }

    private function cloner(): GitCloner
    {
        return new GitCloner(self::STUB, $this->base);
    }

    private function url(string $url): RepositoryUrl
    {
        return RepositoryUrl::fromString($url);
    }

    /**
     * @return list<string>
     */
    private function entries(): array
    {
        return array_values(array_diff((array) scandir($this->base), ['.', '..']));
    }
}
