<?php

declare(strict_types=1);

namespace PhpxComplexity\Config;

/**
 * Configuration de l'analyse, fusionnée depuis les valeurs par défaut et un
 * éventuel fichier JSON (`phpx-complexity.json` à la racine du projet audité).
 */
final class Config
{
    /**
     * @param array<string,float> $thresholds  clé de lentille => seuil
     * @param list<string>        $exclude     fragments de chemin exclus
     * @param list<string>        $qaRequired  clés d'outils QA obligatoires
     */
    private function __construct(
        public readonly array $thresholds,
        public readonly array $exclude,
        public readonly int $top,
        public readonly array $qaRequired = [],
        public readonly ?string $coveragePath = null,
    ) {
    }

    public static function defaults(): self
    {
        return new self(
            thresholds: [
                'cognitive' => 15.0,
                'returns' => 3.0,
                'params' => 7.0,
                'live_peak' => 8.0,
                'entangle' => 4.0,
            ],
            exclude: ['/vendor/', '/node_modules/', '/var/', '/.git/'],
            top: 25,
            qaRequired: [],
            coveragePath: null,
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    public function withOverrides(array $data): self
    {
        $thresholds = $this->thresholds;
        if (isset($data['thresholds']) && is_array($data['thresholds'])) {
            foreach ($data['thresholds'] as $key => $value) {
                if (is_string($key) && is_numeric($value)) {
                    $thresholds[$key] = (float) $value;
                }
            }
        }

        $exclude = $this->exclude;
        if (isset($data['exclude']) && is_array($data['exclude'])) {
            $exclude = array_values(array_filter($data['exclude'], 'is_string'));
        }

        $top = isset($data['top']) && is_numeric($data['top']) ? (int) $data['top'] : $this->top;

        $qaRequired = $this->qaRequired;
        if (isset($data['qa']['required']) && is_array($data['qa']['required'])) {
            $qaRequired = array_values(array_filter($data['qa']['required'], 'is_string'));
        }

        $coveragePath = $this->coveragePath;
        if (isset($data['coverage']['path']) && is_string($data['coverage']['path'])) {
            $coveragePath = $data['coverage']['path'];
        }

        return new self($thresholds, $exclude, $top, $qaRequired, $coveragePath);
    }

    public function threshold(string $lensKey): float
    {
        return $this->thresholds[$lensKey] ?? \PHP_FLOAT_MAX;
    }

    public function isExcluded(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);
        foreach ($this->exclude as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
