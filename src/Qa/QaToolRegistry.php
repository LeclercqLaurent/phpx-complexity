<?php

declare(strict_types=1);

namespace PhpxComplexity\Qa;

/**
 * The default catalogue of recognised QA tools, grouped by category.
 * Couvre l'outillage PHP courant ; extensible sans toucher au reste.
 */
final class QaToolRegistry
{
    /**
     * @return list<QaTool>
     */
    public static function defaults(): array
    {
        return [
            new QaTool('phpstan', 'PHPStan', 'Static analysis', ['phpstan/phpstan'], ['phpstan.neon', 'phpstan.neon.dist', 'phpstan.dist.neon']),
            new QaTool('psalm', 'Psalm', 'Static analysis', ['vimeo/psalm'], ['psalm.xml', 'psalm.xml.dist']),
            new QaTool('phpcsfixer', 'PHP-CS-Fixer', 'Coding standards', ['friendsofphp/php-cs-fixer'], ['.php-cs-fixer.php', '.php-cs-fixer.dist.php', '.php_cs', '.php_cs.dist']),
            new QaTool('phpcs', 'PHP_CodeSniffer', 'Coding standards', ['squizlabs/php_codesniffer'], ['phpcs.xml', 'phpcs.xml.dist', '.phpcs.xml.dist']),
            new QaTool('rector', 'Rector', 'Automated refactoring', ['rector/rector'], ['rector.php']),
            new QaTool('phpunit', 'PHPUnit', 'Tests', ['phpunit/phpunit'], ['phpunit.xml', 'phpunit.xml.dist', 'phpunit.dist.xml']),
            new QaTool('pest', 'Pest', 'Tests', ['pestphp/pest'], ['tests/Pest.php']),
            new QaTool('behat', 'Behat', 'Tests', ['behat/behat'], ['behat.yml', 'behat.yml.dist', 'behat.dist.yml']),
            new QaTool('infection', 'Infection', 'Tests (mutation)', ['infection/infection'], ['infection.json', 'infection.json.dist', 'infection.json5']),
            new QaTool('ci_github', 'GitHub Actions', 'Continuous integration', [], ['.github/workflows']),
            new QaTool('ci_gitlab', 'GitLab CI', 'Continuous integration', [], ['.gitlab-ci.yml']),
            new QaTool('editorconfig', 'EditorConfig', 'Hygiene', [], ['.editorconfig']),
        ];
    }
}
