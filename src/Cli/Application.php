<?php

declare(strict_types=1);

namespace PhpxComplexity\Cli;

use PhpxComplexity\Analyzer\ProjectAnalyzer;
use PhpxComplexity\Config\Config;
use PhpxComplexity\Coverage\CoverageReportReader;
use PhpxComplexity\Coverage\TestPresenceAnalyzer;
use PhpxComplexity\Lens\CognitiveComplexityLens;
use PhpxComplexity\Lens\EntanglementLens;
use PhpxComplexity\Lens\Lens;
use PhpxComplexity\Lens\LiveVariablePeakLens;
use PhpxComplexity\Lens\ParameterCountLens;
use PhpxComplexity\Lens\ReturnCountLens;
use PhpxComplexity\Qa\QaPresenceChecker;
use PhpxComplexity\Qa\QaToolRegistry;
use PhpxComplexity\Report\ConsoleReporter;
use PhpxComplexity\Report\CoverageReporter;
use PhpxComplexity\Report\HtmlReporter;
use PhpxComplexity\Report\JsonReporter;
use PhpxComplexity\Report\QaReporter;

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

        $path = $this->resolvePath($options);
        if (!file_exists($path)) {
            $this->stderr(sprintf('Chemin introuvable : %s', $path));

            return 2;
        }

        $config = $this->loadConfig($path, $options);
        $lenses = $this->buildLenses($config);
        $analyzer = new ProjectAnalyzer($lenses, $config);

        $analysis = $analyzer->analyze($path);
        /** @var list<\PhpxComplexity\Analyzer\MethodResult> $results */
        $results = $analysis['results'];

        $qaReporter = new QaReporter();
        $qaResults = isset($options['qa'])
            ? (new QaPresenceChecker(QaToolRegistry::defaults(), $config->qaRequired))->check($path)
            : [];

        $withCoverage = isset($options['coverage']);
        $coverage = $withCoverage ? (new CoverageReportReader())->read($path, $config->coveragePath) : null;
        $presence = $withCoverage ? (new TestPresenceAnalyzer())->analyze($path) : null;

        if (isset($options['json'])) {
            $this->stdout((new JsonReporter($lenses, $config))->render($results, $analysis['files'], $analysis['parseErrors'], $qaResults, $coverage, $presence));
        } elseif (isset($options['html'])) {
            $html = (new HtmlReporter($lenses, $config))->render($results, $analysis['files'], $analysis['parseErrors'], $qaResults, $coverage, $presence);
            // Précédence : --html=FICHIER (CLI) > html.path (config) > stdout.
            $target = is_string($options['html']) ? $options['html'] : $config->htmlPath;
            if (is_string($target)) {
                $dir = \dirname($target);
                if (!is_dir($dir) && !@mkdir($dir, 0o777, true) && !is_dir($dir)) {
                    $this->stderr(sprintf('Répertoire de sortie introuvable et non créable : %s', $dir));

                    return 2;
                }
                if (false === @file_put_contents($target, $html)) {
                    $this->stderr(sprintf('Écriture impossible : %s', $target));

                    return 2;
                }
                $this->stderr(sprintf('Rapport HTML écrit : %s', $target));
            } else {
                $this->stdout($html);
            }
        } else {
            $reporter = new ConsoleReporter($lenses, $config);
            $this->stdout($reporter->render($results, $analysis['files'], !isset($options['no-divergence'])));
            if (isset($options['qa'])) {
                $this->stdout("\n" . $qaReporter->render($qaResults));
            }
            if (null !== $coverage && null !== $presence) {
                $this->stdout("\n" . (new CoverageReporter())->render($coverage, $presence));
            }
            foreach ($analysis['parseErrors'] as $error) {
                $this->stderr('parse: ' . $error);
            }
        }

        if (isset($options['fail-on-violations'])) {
            $violations = $this->countViolations($results, $lenses, $config)
                + count($qaReporter->missingRequired($qaResults));

            return $violations > 0 ? 1 : 0;
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
            $config = $config->withOverrides($this->decodeConfigFile($file));
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

    /**
     * Chemin audité : l'argument s'il est fourni, sinon le répertoire courant.
     *
     * @param array<string,string|bool|list<string>> $options
     */
    private function resolvePath(array $options): string
    {
        $requested = $options['path'] ?? null;
        if (is_string($requested)) {
            return $requested;
        }
        $cwd = getcwd();

        return is_string($cwd) ? $cwd : '.';
    }

    /**
     * Contenu JSON du fichier de config, réduit aux clés textuelles : un tableau
     * JSON de premier niveau n'est pas une configuration valide.
     *
     * @return array<string,mixed>
     */
    private function decodeConfigFile(string $file): array
    {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (!is_array($decoded)) {
            return [];
        }

        $data = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $data[$key] = $value;
            }
        }

        return $data;
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
              --html[=FICHIER]       Rapport HTML autonome (hors-ligne). Sans valeur :
                                     sortie standard ; avec =FICHIER : écrit le fichier
              --qa                   Vérifie la présence des outils de QA du projet
              --coverage             Lit un rapport de couverture (clover/cobertura) s'il
                                     existe + faits de présence de tests (statique)
              --config=FICHIER       Fichier de config (défaut : phpx-complexity.json)
              --fail-on-violations   Code de sortie 1 si un seuil est dépassé, ou si un
                                     outil QA requis manque (mode gate)
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
