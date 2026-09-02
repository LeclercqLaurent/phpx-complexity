<?php

declare(strict_types=1);

namespace PhpxComplexity\Cli;

/**
 * Options de la ligne de commande, figées en propriétés typées.
 *
 * Le parsing est le seul endroit qui manipule des chaînes brutes : partout
 * ailleurs on lit une propriété dont le type est garanti, ce qui évite de
 * repasser des unions `string|bool|list<string>` à travers l'application.
 */
final class Options
{
    /** @var list<string> */
    public readonly array $exclude;

    public readonly ?string $path;
    public readonly bool $help;
    public readonly bool $json;
    public readonly bool $html;
    public readonly ?string $htmlTarget;
    public readonly bool $qa;
    public readonly bool $coverage;
    public readonly bool $failOnViolations;
    public readonly bool $showDivergence;
    public readonly ?string $configFile;
    public readonly ?int $top;

    /**
     * @param list<string> $args arguments bruts, nom du binaire exclu
     */
    public function __construct(array $args)
    {
        $raw = self::parse($args);
        $top = self::text($raw, 'top');

        $this->path = self::text($raw, 'path');
        $this->help = isset($raw['help']);
        $this->json = isset($raw['json']);
        $this->html = isset($raw['html']);
        $this->htmlTarget = self::text($raw, 'html');
        $this->qa = isset($raw['qa']);
        $this->coverage = isset($raw['coverage']);
        $this->failOnViolations = isset($raw['fail-on-violations']);
        $this->showDivergence = !isset($raw['no-divergence']);
        $this->configFile = self::text($raw, 'config');
        $this->top = null === $top ? null : (int) $top;
        $this->exclude = self::texts($raw, 'exclude');
    }

    /**
     * @param list<string> $args
     *
     * @return array<string,string|bool|list<string>>
     */
    private static function parse(array $args): array
    {
        $options = [];
        foreach ($args as $arg) {
            if ('-h' === $arg || '--help' === $arg) {
                $options['help'] = true;
            } elseif ('--json' === $arg) {
                $options['json'] = true;
            } elseif ('--html' === $arg) {
                $options['html'] = true;
            } elseif (str_starts_with($arg, '--html=')) {
                $options['html'] = substr($arg, 7);
            } elseif ('--no-divergence' === $arg) {
                $options['no-divergence'] = true;
            } elseif ('--qa' === $arg) {
                $options['qa'] = true;
            } elseif ('--coverage' === $arg) {
                $options['coverage'] = true;
            } elseif ('--fail-on-violations' === $arg) {
                $options['fail-on-violations'] = true;
            } elseif (str_starts_with($arg, '--exclude=')) {
                $options['exclude'][] = substr($arg, 10);
            } elseif (str_starts_with($arg, '--config=')) {
                $options['config'] = substr($arg, 9);
            } elseif (str_starts_with($arg, '--top=')) {
                $options['top'] = substr($arg, 6);
            } elseif (!str_starts_with($arg, '-')) {
                $options['path'] = $arg;
            }
        }

        return $options;
    }

    /**
     * @param array<string,string|bool|list<string>> $raw
     */
    private static function text(array $raw, string $key): ?string
    {
        $value = $raw[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param array<string,string|bool|list<string>> $raw
     *
     * @return list<string>
     */
    private static function texts(array $raw, string $key): array
    {
        $value = $raw[$key] ?? null;

        return is_array($value) ? $value : [];
    }
}
