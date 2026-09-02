<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Baseline;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Baseline\Exception\BaselineException;
use PhpxComplexity\Baseline\Snapshot;

final class SnapshotTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../fixtures/baseline';

    public function testIdentityIgnoresTheLineNumber(): void
    {
        $snapshot = Snapshot::fromFile(self::FIXTURES . '/reference.json');

        self::assertArrayHasKey('src/A.php::stable', $snapshot->methods);
        self::assertSame(10, $snapshot->methods['src/A.php::stable']->line);
    }

    public function testHomonymsInTheSameFileAreRankedByLine(): void
    {
        $snapshot = Snapshot::fromFile(self::FIXTURES . '/homonyms.json');

        // Rang attribué dans l'ordre des lignes, pas dans celui du fichier JSON.
        self::assertSame(10, $snapshot->methods['src/Two.php::render']->line);
        self::assertSame(40, $snapshot->methods['src/Two.php::render#2']->line);
    }

    public function testThresholdsAreReadFromTheLensSection(): void
    {
        $snapshot = Snapshot::fromFile(self::FIXTURES . '/reference.json');

        self::assertSame(['cognitive' => 15.0], $snapshot->thresholds);
    }

    public function testMissingFileIsRejected(): void
    {
        $this->expectException(BaselineException::class);

        Snapshot::fromFile(self::FIXTURES . '/absent.json');
    }

    public function testMalformedPayloadIsRejected(): void
    {
        $this->expectException(BaselineException::class);
        $this->expectExceptionMessageMatches('/Baseline invalide/');

        Snapshot::fromFile(__DIR__ . '/../fixtures/baseline-project/broken-baseline.json');
    }
}
