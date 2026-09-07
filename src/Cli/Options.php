<?php

declare(strict_types=1);

namespace PhpxComplexity\Cli;

/**
 * The command-line options, frozen into typed properties.
 *
 * Parsing is the only place that handles raw strings: everywhere else a property
 * with a guaranteed type is read, which avoids passing `string|bool|list<string>`
 * unions back and forth through the application.
 */
final class Options
{
    /** @var list<string> */
    public readonly array $exclude;

    /**
     * Options non reconnues. Les ignorer en silence ferait passer une faute de
     * typo for a success: "--jsno" would render a console report with an
     * code 0, et la CI croirait avoir du JSON.
     *
     * @var list<string>
     */
    public readonly array $unknown;

    public readonly Command $command;
    /** The local path to audit, or the repository URL under the "fetch" subcommand. */
    public readonly ?string $target;
    public readonly bool $keep;
    public readonly bool $help;
    public readonly bool $json;
    public readonly bool $html;
    public readonly ?string $htmlTarget;
    public readonly bool $qa;
    public readonly bool $coverage;
    public readonly bool $failOnViolations;
    public readonly bool $showDivergence;
    public readonly ?string $configFile;
    public readonly ?string $baselineFile;
    public readonly ?string $baselineOut;
    public readonly bool $failOnNew;
    public readonly ?int $top;

    /**
     * @param list<string> $args arguments bruts, nom du binaire exclu
     */
    public function __construct(array $args)
    {
        $raw = self::parse($args);
        $top = self::text($raw, 'top');
        $positionals = self::texts($raw, 'positionals');
        $verb = isset($positionals[0]) ? Command::tryFrom($positionals[0]) : null;

        $this->command = $verb ?? Command::Audit;
        // The verb, when present, consumes the first positional argument.
        $this->target = $positionals[null === $verb ? 0 : 1] ?? null;
        $this->keep = isset($raw['keep']);
        $this->help = isset($raw['help']);
        $this->json = isset($raw['json']);
        $this->html = isset($raw['html']);
        $this->htmlTarget = self::text($raw, 'html');
        $this->qa = isset($raw['qa']);
        $this->coverage = isset($raw['coverage']);
        $this->failOnViolations = isset($raw['fail-on-violations']);
        $this->showDivergence = !isset($raw['no-divergence']);
        $this->configFile = self::text($raw, 'config');
        $this->baselineFile = self::text($raw, 'baseline');
        $this->baselineOut = self::text($raw, 'baseline-out');
        $this->failOnNew = isset($raw['fail-on-new']);
        $this->top = null === $top ? null : (int) $top;
        $this->exclude = self::texts($raw, 'exclude');
        $this->unknown = self::texts($raw, 'unknown');
    }

    /**
     * Valueless flags: accepted label => internal key.
     */
    private const FLAGS = [
        '-h' => 'help',
        '--help' => 'help',
        '--json' => 'json',
        '--html' => 'html',
        '--no-divergence' => 'no-divergence',
        '--qa' => 'qa',
        '--coverage' => 'coverage',
        '--fail-on-violations' => 'fail-on-violations',
        '--fail-on-new' => 'fail-on-new',
        '--keep' => 'keep',
    ];

    /**
     * Options taking a value: prefix => internal key.
     */
    private const VALUED = [
        '--html=' => 'html',
        '--exclude=' => 'exclude',
        '--config=' => 'config',
        '--baseline=' => 'baseline',
        '--baseline-out=' => 'baseline-out',
        '--top=' => 'top',
    ];

    /**
     * Keys whose every occurrence adds to the previous ones.
     */
    private const REPEATABLE = ['exclude'];

    /**
     * A table rather than a chain of conditions: adding an option becomes one
     * constant line, and the reading cost no longer grows with the number of
     * accepted options.
     *
     * @param list<string> $args
     *
     * @return array<string,string|bool|list<string>>
     */
    private static function parse(array $args): array
    {
        $options = [];
        $repeated = [];
        foreach ($args as $arg) {
            if (isset(self::FLAGS[$arg])) {
                $options[self::FLAGS[$arg]] = true;
                continue;
            }

            $valued = self::valued($arg);
            if (null === $valued) {
                // Tout ce qui ne commence pas par un tiret est positionnel :
                // an optional verb, then the target.
                $repeated[str_starts_with($arg, '-') ? 'unknown' : 'positionals'][] = $arg;
                continue;
            }

            [$key, $value] = $valued;
            if (in_array($key, self::REPEATABLE, true)) {
                $repeated[$key][] = $value;
                continue;
            }

            $options[$key] = $value;
        }

        return $options + $repeated;
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private static function valued(string $arg): ?array
    {
        foreach (self::VALUED as $prefix => $key) {
            if (str_starts_with($arg, $prefix)) {
                return [$key, substr($arg, strlen($prefix))];
            }
        }

        return null;
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
