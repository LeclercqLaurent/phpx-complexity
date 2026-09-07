<?php

declare(strict_types=1);

namespace PhpxComplexity\Config;

/**
 * The analysis configuration, merged from the defaults and an optional JSON
 * file (`phpx-complexity.json` at the root of the audited project).
 */
final class Config
{
    /**
     * @param array<string,float> $thresholds  lens key => threshold
     * @param list<string>        $exclude     fragments de chemin exclus
     * @param list<string>        $qaRequired  keys of mandatory QA tools
     */
    private function __construct(
        public readonly array $thresholds,
        public readonly array $exclude,
        public readonly int $top,
        public readonly array $qaRequired = [],
        public readonly ?string $coveragePath = null,
        public readonly ?string $htmlPath = null,
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
                'entangle' => 3.0,
            ],
            exclude: ['/vendor/', '/node_modules/', '/var/', '/.git/'],
            top: 25,
            qaRequired: [],
            coveragePath: null,
            htmlPath: null,
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

        $qaRequired = self::nestedStringList($data, 'qa', 'required') ?? $this->qaRequired;
        $coveragePath = self::nestedString($data, 'coverage', 'path') ?? $this->coveragePath;
        $htmlPath = self::nestedString($data, 'html', 'path') ?? $this->htmlPath;

        return new self($thresholds, $exclude, $top, $qaRequired, $coveragePath, $htmlPath);
    }

    /**
     * The nested value `data[section][key]`, only when it really is a string.
     *
     * @param array<string,mixed> $data
     */
    private static function nestedString(array $data, string $section, string $key): ?string
    {
        $value = self::nested($data, $section, $key);

        return is_string($value) ? $value : null;
    }

    /**
     * The same, for a list of strings (non-string entries are discarded).
     *
     * @param array<string,mixed> $data
     *
     * @return list<string>|null
     */
    private static function nestedStringList(array $data, string $section, string $key): ?array
    {
        $value = self::nested($data, $section, $key);

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : null;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function nested(array $data, string $section, string $key): mixed
    {
        $sub = $data[$section] ?? null;

        return is_array($sub) ? ($sub[$key] ?? null) : null;
    }

    public function threshold(string $lensKey): float
    {
        return $this->thresholds[$lensKey] ?? \PHP_FLOAT_MAX;
    }

    /**
     * Fragments are compared against the path RELATIVE to the audited root,
     * prefixed with a "/" so that a fragment such as `/vendor/` matches a whole
     * top-level segment. Comparing them to the absolute path would empty the
     * audit of a project installed under /var/www, /tests/ or any namesake.
     */
    public function isExcluded(string $relativePath): bool
    {
        $normalized = '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
        foreach ($this->exclude as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
