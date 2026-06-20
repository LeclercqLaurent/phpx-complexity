<?php

declare(strict_types=1);

namespace PhpxComplexity\Qa;

/**
 * Catalogue par défaut des outils de QA reconnus, regroupés par catégorie.
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
            new QaTool('phpstan', 'PHPStan', 'Analyse statique', ['phpstan/phpstan'], ['phpstan.neon', 'phpstan.neon.dist', 'phpstan.dist.neon']),
            new QaTool('psalm', 'Psalm', 'Analyse statique', ['vimeo/psalm'], ['psalm.xml', 'psalm.xml.dist']),
            new QaTool('phpcsfixer', 'PHP-CS-Fixer', 'Standards de code', ['friendsofphp/php-cs-fixer'], ['.php-cs-fixer.php', '.php-cs-fixer.dist.php', '.php_cs', '.php_cs.dist']),
            new QaTool('phpcs', 'PHP_CodeSniffer', 'Standards de code', ['squizlabs/php_codesniffer'], ['phpcs.xml', 'phpcs.xml.dist', '.phpcs.xml.dist']),
            new QaTool('rector', 'Rector', 'Refactoring auto', ['rector/rector'], ['rector.php']),
            new QaTool('phpunit', 'PHPUnit', 'Tests', ['phpunit/phpunit'], ['phpunit.xml', 'phpunit.xml.dist', 'phpunit.dist.xml']),
            new QaTool('pest', 'Pest', 'Tests', ['pestphp/pest'], ['tests/Pest.php']),
            new QaTool('behat', 'Behat', 'Tests', ['behat/behat'], ['behat.yml', 'behat.yml.dist', 'behat.dist.yml']),
            new QaTool('infection', 'Infection', 'Tests (mutation)', ['infection/infection'], ['infection.json', 'infection.json.dist', 'infection.json5']),
            new QaTool('ci_github', 'GitHub Actions', 'Intégration continue', [], ['.github/workflows']),
            new QaTool('ci_gitlab', 'GitLab CI', 'Intégration continue', [], ['.gitlab-ci.yml']),
            new QaTool('editorconfig', 'EditorConfig', 'Hygiène', [], ['.editorconfig']),
        ];
    }
}
