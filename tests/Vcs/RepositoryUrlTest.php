<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Vcs;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PhpxComplexity\Vcs\Exception\GitException;
use PhpxComplexity\Vcs\RepositoryUrl;

/**
 * L'URL vient de l'utilisateur et finit passée à git : cette validation est la
 * frontière de sécurité du module.
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
            ['ext::sh -c whoami', 'transport ext, exécution de commande'],
            ['git://github.com/vendor/projet.git', 'ni chiffré ni authentifié'],
            ['file:///etc', 'un dossier local s\'analyse directement'],
            ['/etc/passwd', 'chemin local'],
            ['https://', 'schéma sans dépôt'],
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
        $this->expectExceptionMessage('URL de dépôt non supportée');

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
