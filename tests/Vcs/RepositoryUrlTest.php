<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Vcs;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PhpxComplexity\Vcs\Exception\GitException;
use PhpxComplexity\Vcs\RepositoryUrl;

/**
 * The URL comes from the user and ends up handed to git: this validation is the
 * security boundary of the module.
 */
final class RepositoryUrlTest extends TestCase
{
    /**
     * @return list<array{0:string}>
     */
    public static function accepted(): array
    {
        return [
            ['https://github.com/vendor/projet.git'],
            ['ssh://git@github.com/vendor/projet.git'],
            ['git@github.com:vendor/projet.git'],
        ];
    }

    /**
     * @return list<array{0:string,1:string}>
     */
    public static function rejected(): array
    {
        return [
            ['--upload-pack=touch /tmp/pwned', 'pris pour une option de git'],
            ['-c', 'pris pour une option de git'],
            ['ext::sh -c whoami', 'ext transport, arbitrary command execution'],
            ['git://github.com/vendor/projet.git', 'neither encrypted nor authenticated'],
            ['file:///etc', 'un dossier local s\'analyse directement'],
            ['/etc/passwd', 'chemin local'],
            ['https://', 'a scheme with no repository'],
            ['', 'vide'],
        ];
    }

    #[DataProvider('accepted')]
    public function testAcceptsRemoteSchemes(string $url): void
    {
        self::assertSame($url, RepositoryUrl::fromString($url)->value);
    }

    #[DataProvider('rejected')]
    public function testRejects(string $url, string $why): void
    {
        $this->expectException(GitException::class);
        $this->expectExceptionMessage('Unsupported repository URL');

        RepositoryUrl::fromString($url);
    }

    public function testNameIsDerivedFromTheUrlForReadableTemporaryDirectories(): void
    {
        self::assertSame('projet', RepositoryUrl::fromString('https://github.com/vendor/projet.git')->name());
        self::assertSame('projet', RepositoryUrl::fromString('git@github.com:vendor/projet')->name());
    }

    public function testSurroundingWhitespaceIsTrimmed(): void
    {
        self::assertSame(
            'https://github.com/vendor/projet.git',
            RepositoryUrl::fromString("  https://github.com/vendor/projet.git\n")->value,
        );
    }
}
