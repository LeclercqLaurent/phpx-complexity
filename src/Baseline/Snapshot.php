<?php

declare(strict_types=1);

namespace PhpxComplexity\Baseline;

use PhpxComplexity\Audit\AuditResult;
use PhpxComplexity\Baseline\Exception\BaselineException;
use PhpxComplexity\Config\Config;
use PhpxComplexity\Lens\Lens;

/**
 * A comparable snapshot, indexed by method identity.
 *
 * The file format is that of the "--json" output: no bespoke format is invented
 * for the baseline, an existing report is simply frozen.
 */
final class Snapshot
{
    /**
     * @param array<string,MethodSnapshot> $methods    indexed by identity key
     * @param array<string,float>          $thresholds thresholds at snapshot time
     */
    private function __construct(
        public readonly array $methods,
        public readonly array $thresholds,
    ) {
    }

    /**
     * @throws BaselineException
     */
    public static function fromFile(string $file): self
    {
        $raw = @file_get_contents($file);
        if (false === $raw) {
            throw BaselineException::unreadable($file);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !is_array($decoded['methods'] ?? null)) {
            throw BaselineException::malformed($file);
        }

        $rows = [];
        foreach ($decoded['methods'] as $method) {
            $row = self::row($method);
            if (null !== $row) {
                $rows[] = $row;
            }
        }

        return new self(self::index($rows), self::thresholds($decoded['lenses'] ?? null));
    }

    /**
     * @param list<Lens> $lenses
     */
    public static function fromAudit(AuditResult $audit, Config $config, array $lenses): self
    {
        $rows = [];
        foreach ($audit->results as $result) {
            $rows[] = [
                'file' => $result->file,
                'name' => $result->name,
                'line' => $result->line,
                'metrics' => $result->metrics,
            ];
        }

        $thresholds = [];
        foreach ($lenses as $lens) {
            $thresholds[$lens->key()] = $config->threshold($lens->key());
        }

        return new self(self::index($rows), $thresholds);
    }

    /**
     * Serialises the snapshot, narrowed to what the comparison REALLY reads:
     * identity, line and raw values, plus the thresholds of the moment.
     *
     * Les rangs centiles et la divergence de la sortie `--json` en sont absents
     * deliberately: they are ranks RELATIVE to the analysed batch, so they get
     * rewritten for every method as soon as one moves. Keeping them would make
     * each regeneration unreadable in review, and they play no part in comparing.
     *
     * The order is that of identity, not of divergence: an addition inserts one
     * block instead of reshuffling everything. The result stays a subset
     * valide du contrat `--json`, que le lecteur continue d'accepter entier.
     */
    public function toJson(): string
    {
        $methods = [];
        foreach ($this->methods as $method) {
            $methods[] = [
                'file' => $method->file,
                'name' => $method->name,
                'line' => $method->line,
                'metrics' => $method->metrics,
            ];
        }

        $lenses = [];
        foreach ($this->thresholds as $key => $threshold) {
            $lenses[$key] = ['threshold' => $threshold];
        }

        return (string) json_encode(
            ['tool' => 'phpx-complexity', 'lenses' => $lenses, 'methods' => $methods],
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * The identity key: file plus name, never the line, which shifts on the
     * slightest addition upstream and would make a whole file look rewritten.
     * The rare namesakes within one file (several classes) are separated by a
     * rank assigned in line order.
     *
     * @param list<array{file:string,name:string,line:int,metrics:array<string,float>}> $rows
     *
     * @return array<string,MethodSnapshot>
     */
    private static function index(array $rows): array
    {
        usort($rows, static fn (array $a, array $b): int => [$a['file'], $a['name'], $a['line']] <=> [$b['file'], $b['name'], $b['line']]);

        $ranks = [];
        $methods = [];
        foreach ($rows as $row) {
            $base = $row['file'] . '::' . $row['name'];
            $rank = ($ranks[$base] ?? 0) + 1;
            $ranks[$base] = $rank;
            $key = 1 === $rank ? $base : $base . '#' . $rank;
            $methods[$key] = new MethodSnapshot($key, $row['file'], $row['name'], $row['line'], $row['metrics']);
        }

        return $methods;
    }

    /**
     * @return array{file:string,name:string,line:int,metrics:array<string,float>}|null
     */
    private static function row(mixed $method): ?array
    {
        if (!is_array($method)) {
            return null;
        }

        $file = $method['file'] ?? null;
        $name = $method['name'] ?? null;
        if (!is_string($file) || !is_string($name)) {
            return null;
        }

        $line = $method['line'] ?? null;

        return [
            'file' => $file,
            'name' => $name,
            'line' => is_int($line) ? $line : 0,
            'metrics' => self::floats($method['metrics'] ?? null),
        ];
    }

    /**
     * @return array<string,float>
     */
    private static function thresholds(mixed $lenses): array
    {
        if (!is_array($lenses)) {
            return [];
        }

        $thresholds = [];
        foreach ($lenses as $key => $lens) {
            $threshold = is_array($lens) ? ($lens['threshold'] ?? null) : null;
            if (is_string($key) && is_numeric($threshold)) {
                $thresholds[$key] = (float) $threshold;
            }
        }

        return $thresholds;
    }

    /**
     * @return array<string,float>
     */
    private static function floats(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $floats = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && is_numeric($item)) {
                $floats[$key] = (float) $item;
            }
        }

        return $floats;
    }
}
