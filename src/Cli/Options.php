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

    public readonly Command $command;
    /** Chemin local à auditer, ou URL du dépôt en sous-commande « fetch ». */
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
        // Le verbe, s'il est présent, consomme le premier argument positionnel.
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
        $this->failOnNew = isset($raw['fail-on-new']);
        $this->top = null === $top ? null : (int) $top;
        $this->exclude = self::texts($raw, 'exclude');
    }

    /**
     * Drapeaux sans valeur : libellé accepté => clé interne.
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
     * Options à valeur : préfixe => clé interne.
     */
    private const VALUED = [
        '--html=' => 'html',
        '--exclude=' => 'exclude',
        '--config=' => 'config',
        '--baseline=' => 'baseline',
        '--top=' => 'top',
    ];

    /**
     * Clés dont chaque occurrence s'ajoute aux précédentes.
     */
    private const REPEATABLE = ['exclude'];

    /**
     * Une table plutôt qu'une chaîne de conditions : ajouter une option devient
     * une ligne de constante, et le coût de lecture ne croît plus avec le nombre
     * d'options acceptées.
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
                // un verbe éventuel, puis la cible.
                if (!str_starts_with($arg, '-')) {
                    $repeated['positionals'][] = $arg;
                }
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
