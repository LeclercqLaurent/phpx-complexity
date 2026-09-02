<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Qa;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Qa\QaPresenceChecker;
use PhpxComplexity\Qa\QaToolRegistry;
use PhpxComplexity\Qa\QaToolResult;

final class QaPresenceCheckerTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/sample-project';

    public function testDetectsToolViaComposerDependency(): void
    {
        self::assertTrue($this->resultFor('phpstan')->present, 'PHPStan déclaré en require-dev');
        self::assertContains('composer:phpstan/phpstan', $this->resultFor('phpstan')->evidence);
    }

    public function testDetectsToolViaConfigFile(): void
    {
        // PHPUnit n'est pas dans composer.json mais phpunit.xml.dist existe.
        self::assertTrue($this->resultFor('phpunit')->present);
        self::assertContains('phpunit.xml.dist', $this->resultFor('phpunit')->evidence);
    }

    public function testReportsAbsentTool(): void
    {
        self::assertFalse($this->resultFor('psalm')->present);
        self::assertSame([], $this->resultFor('psalm')->evidence);
    }

    public function testRequiredFlagAndMissingDetection(): void
    {
        $checker = new QaPresenceChecker(QaToolRegistry::defaults(), ['phpstan', 'psalm']);
        $results = $checker->check(self::FIXTURE);

        $phpstan = $this->find($results, 'phpstan');
        $psalm = $this->find($results, 'psalm');

        self::assertTrue($phpstan->required);
        self::assertTrue($psalm->required);
        self::assertTrue($phpstan->present);
        self::assertFalse($psalm->present, 'Psalm requis mais absent → gate doit échouer');
    }

    private function resultFor(string $key): QaToolResult
    {
        $checker = new QaPresenceChecker(QaToolRegistry::defaults(), []);

        return $this->find($checker->check(self::FIXTURE), $key);
    }

    /**
     * @param list<QaToolResult> $results
     */
    private function find(array $results, string $key): QaToolResult
    {
        foreach ($results as $result) {
            if ($result->tool->key === $key) {
                return $result;
            }
        }

        self::fail("Outil introuvable : {$key}");
    }
}
