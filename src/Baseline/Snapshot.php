<?php

declare(strict_types=1);

namespace PhpxComplexity\Baseline;

use PhpxComplexity\Audit\AuditResult;
use PhpxComplexity\Baseline\Exception\BaselineException;
use PhpxComplexity\Config\Config;
use PhpxComplexity\Lens\Lens;

/**
 * Instantané comparable, indexé par identité de méthode.
 *
 * Le format de fichier est celui de la sortie « --json » : aucun format propre
 * n'est inventé pour la baseline, on fige un rapport existant.
 */
final class Snapshot
{
    /**
     * @param array<string,MethodSnapshot> $methods    indexés par clé d'identité
     * @param array<string,float>          $thresholds seuils au moment de l'instantané
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
     * Clé d'identité : fichier + nom, jamais la ligne — celle-ci se décale au
     * moindre ajout en amont et ferait passer un fichier entier pour réécrit.
     * Les rares homonymes d'un même fichier (plusieurs classes) sont départagés
     * par un rang attribué dans l'ordre des lignes.
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
