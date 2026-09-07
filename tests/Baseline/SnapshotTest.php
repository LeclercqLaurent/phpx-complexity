<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Baseline;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Baseline\Exception\BaselineException;
use PhpxComplexity\Baseline\Snapshot;
use PhpxComplexity\Config\Config;
use PhpxComplexity\Lens\LensRegistry;
use PhpxComplexity\Tests\Support\SampleAudit;

final class SnapshotTest extends TestCase
{
    use SampleAudit;

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

        // The rank is assigned in line order, not in JSON file order.
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
        $this->expectExceptionMessageMatches('/Invalid baseline/');

        Snapshot::fromFile(__DIR__ . '/../fixtures/baseline-project/broken-baseline.json');
    }

    /**
     * The written snapshot keeps only what the comparison reads. Percentile
     * centiles et la divergence en sont exclus : relatifs au lot, ils seraient
     * rewritten for every method as soon as a single one moves.
     */
    public function testSerialisedSnapshotDropsWhatComparisonDoesNotRead(): void
    {
        $json = $this->serialised();

        self::assertStringNotContainsString('percentile', $json);
        self::assertStringNotContainsString('divergence', $json);
        self::assertStringNotContainsString('violations', $json);
        self::assertStringNotContainsString('summary', $json);
        self::assertStringContainsString('"metrics"', $json);
        self::assertStringContainsString('"threshold"', $json);
    }

    /**
     * Identity order rather than divergence order: an addition inserts a block
     * de redistribuer tout le fichier.
     */
    public function testSerialisedMethodsAreOrderedByIdentity(): void
    {
        /** @var array{methods: list<array{file:string,name:string}>} $decoded */
        $decoded = json_decode($this->serialised(), true, 512, \JSON_THROW_ON_ERROR);
        $keys = array_map(
            static fn (array $m): string => $m['file'] . '::' . $m['name'],
            $decoded['methods'],
        );

        $sorted = $keys;
        sort($sorted);
        self::assertSame($sorted, $keys);
    }

    public function testSerialisedSnapshotIsReadableBackWithoutLoss(): void
    {
        $directory = __DIR__ . '/../../var';
        @mkdir($directory, 0o755, true);
        $file = $directory . '/snapshot-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($file, $this->serialised());

        try {
            $reread = Snapshot::fromFile($file);
            $original = $this->snapshot();

            self::assertSame(array_keys($original->methods), array_keys($reread->methods));
            self::assertSame($original->thresholds, $reread->thresholds);
            self::assertSame(
                $original->methods['src/Foo.php::compute']->metrics,
                $reread->methods['src/Foo.php::compute']->metrics,
            );
        } finally {
            @unlink($file);
        }
    }

    private function snapshot(): Snapshot
    {
        $config = Config::defaults();

        return Snapshot::fromAudit($this->audit(), $config, LensRegistry::defaults($config));
    }

    private function serialised(): string
    {
        return $this->snapshot()->toJson();
    }
}
