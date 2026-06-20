<?php

declare(strict_types=1);

namespace PhpxComplexity\Cli;

use PhpxComplexity\Analyzer\ProjectAnalyzer;
use PhpxComplexity\Config\Config;
use PhpxComplexity\Lens\CognitiveComplexityLens;
use PhpxComplexity\Lens\EntanglementLens;
use PhpxComplexity\Lens\Lens;
use PhpxComplexity\Lens\LiveVariablePeakLens;
use PhpxComplexity\Lens\ParameterCountLens;
use PhpxComplexity\Lens\ReturnCountLens;
use PhpxComplexity\Report\ConsoleReporter;
use PhpxComplexity\Report\JsonReporter;

/**
 * Point d'entrée CLI. Codes de sortie : 0 = OK, 1 = violations (mode gate),
 * 2 = erreur d'usage / d'E-S.
 */
final class Application
{
    public const VERSION = '0.1.0';

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $options = $this->parse(array_slice($argv, 1));
        if (isset($options['help'])) {
            $this->stdout($this->usage());

            return 0;
        }

        $path = $options['path'] ?? getcwd();
        if (!is_string($path) || !file_exists($path)) {
            $this->stderr(sprintf('Chemin introuvable : %s', (string) $path));

            return 2;
        }

        $config = $this->loadConfig($path, $options);
        $lenses = $this->buildLenses($config);
        $analyzer = new ProjectAnalyzer($lenses, $config);

        $analysis = $analyzer->analyze($path);
        /** @var list<\PhpxComplexity\Analyzer\MethodResult> $results */
        $results = $analysis['results'];

        if (isset($options['json'])) {
            $this->stdout((new JsonReporter($lenses, $config))->render($results, $analysis['files'], $analysis['parseErrors']));
        } else {
            $reporter = new ConsoleReporter($lenses, $config);
            $this->stdout($reporter->render($results, $analysis['files'], !isset($options['no-divergence'])));
            foreach ($analysis['parseErrors'] as $error) {
                $this->stderr('parse: ' . $error);
            }
        }

        if (isset($options['fail-on-violations'])) {
            return $this->countViolations($results, $lenses, $config) > 0 ? 1 : 0;
        }

        return 0;
    }

    /**
     * @param list<Lens> $lenses
     * @param list<\PhpxComplexity\Analyzer\MethodResult> $results
     */
    private function countViolations(array $results, array $lenses, Config $config): int
    {
        $count = 0;
        foreach ($results as $result) {
            foreach ($lenses as $lens) {
                if ($result->metric($lens->key()) > $config->threshold($lens->key())) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    /**
     * @return list<Lens>
     */
    private function buildLenses(Config $config): array
    {
        return [
            new CognitiveComplexityLens($config->threshold('cognitive')),
            new ParameterCountLens($config->threshold('params')),
            new ReturnCountLens($config->threshold('returns')),
            new LiveVariablePeakLens($config->threshold('live_peak')),
            new EntanglementLens($config->threshold('entangle')),
        ];
    }

    /**
     * @param array<string,string|bool|list<string>> $options
     */
    private function loadConfig(string $path, array $options): Config
    {
        $config = Config::defaults();

        $file = $options['config'] ?? $this->autoDetectConfig($path);
        if (is_string($file) && is_file($file)) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                $config = $config->withOverrides($data);
            }
        }

        $cliOverrides = [];
        if (isset($options['top']) && is_string($options['top'])) {
            $cliOverrides['top'] = (int) $options['top'];
        }
        if (isset($options['exclude']) && is_array($options['exclude'])) {
            $cliOverrides['exclude'] = array_merge($config->exclude, $options['exclude']);
        }

        return [] === $cliOverrides ? $config : $config->withOverrides($cliOverrides);
    }

    private function autoDetectConfig(string $path): ?string
    {
        $base = is_file($path) ? dirname($path) : $path;
        foreach (['phpx-complexity.json', '.phpx-complexity.json'] as $name) {
            $candidate = $base . '/' . $name;
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param list<string> $args
     *
     * @return array<string,string|bool|list<string>>
     */
    private function parse(array $args): array
    {
        $options = [];
        foreach ($args as $arg) {
            if ('-h' === $arg || '--help' === $arg) {
                $options['help'] = true;
            } elseif ('--json' === $arg) {
                $options['json'] = true;
            } elseif ('--no-divergence' === $arg) {
                $options['no-divergence'] = true;
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

    private function usage(): string
    {
        $version = self::VERSION;

        return <<<TXT
            phpx-complexity {$version} — auditeur de complexité PHP multi-lentilles

            USAGE
              phpx-complexity [CHEMIN] [options]

            LENTILLES
              cognitive  S3776 complexité cognitive (branches + imbrication)
              params     S107  nombre de paramètres
              returns    S1142 nombre d'instructions return
              live_peak  pic de variables vivantes (mémoire de travail)
              entangle   intrication des données (degré du graphe de co-occurrence)

            OPTIONS
              --json                 Sortie JSON (CI, dashboards)
              --config=FICHIER       Fichier de config (défaut : phpx-complexity.json)
              --fail-on-violations   Code de sortie 1 si un seuil est dépassé (mode gate)
              --top=N                Nombre de lignes du classement
              --exclude=FRAGMENT     Exclut les chemins contenant FRAGMENT (répétable)
              --no-divergence        Masque le rapport de divergence
              -h, --help             Cette aide

            Le rapport de DIVERGENCE met en avant les méthodes où les lentilles se
            contredisent — l'angle mort des métriques de complexité isolées.

            TXT;
    }

    private function stdout(string $text): void
    {
        fwrite(\STDOUT, $text);
    }

    private function stderr(string $text): void
    {
        fwrite(\STDERR, $text . "\n");
    }
}
