<?php

declare(strict_types=1);

namespace PhpxComplexity\Cli;

use PhpxComplexity\Audit\AuditResult;
use PhpxComplexity\Audit\AuditRunner;
use PhpxComplexity\Baseline\BaselineComparator;
use PhpxComplexity\Baseline\Comparison;
use PhpxComplexity\Baseline\Exception\BaselineException;
use PhpxComplexity\Baseline\Snapshot;
use PhpxComplexity\Config\Config;
use PhpxComplexity\Lens\Lens;
use PhpxComplexity\Lens\LensRegistry;
use PhpxComplexity\Report\ConsoleReporter;
use PhpxComplexity\Report\CoverageReporter;
use PhpxComplexity\Report\DeltaReporter;
use PhpxComplexity\Report\HtmlReporter;
use PhpxComplexity\Report\JsonReporter;
use PhpxComplexity\Report\QaReporter;
use PhpxComplexity\Vcs\Checkout;
use PhpxComplexity\Vcs\Exception\GitException;
use PhpxComplexity\Vcs\GitCloner;
use PhpxComplexity\Vcs\RepositoryUrl;

/**
 * Point d'entrée CLI : résout les options et le chemin, délègue l'audit, choisit
 * un rapport, calcule le code de sortie. Aucune logique de mesure ici.
 *
 * Codes de sortie : 0 = OK, 1 = violations (mode gate), 2 = erreur d'usage ou d'E-S.
 */
final class Application
{
    public const VERSION = '0.1.0';

    public function __construct(private readonly GitCloner $cloner = new GitCloner())
    {
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $options = new Options(array_slice($argv, 1));
        if ($options->help) {
            $this->stdout($this->usage());

            return 0;
        }

        $error = $this->usageError($options);
        if (null !== $error) {
            $this->stderr($error);

            return 2;
        }

        return Command::Fetch === $options->command
            ? $this->fetch($options)
            : $this->audit($this->resolvePath($options), $options);
    }

    /**
     * Invocation incohérente : message d'erreur, ou null si elle tient debout.
     */
    private function usageError(Options $options): ?string
    {
        if ($options->failOnNew && null === $options->baselineFile) {
            return '--fail-on-new attend une référence : ajouter --baseline=FICHIER.';
        }

        if (Command::Fetch === $options->command) {
            return null === $options->target ? 'La sous-commande « fetch » attend une URL de dépôt.' : null;
        }

        $path = $this->resolvePath($options);

        return file_exists($path) ? null : sprintf('Chemin introuvable : %s', $path);
    }

    /**
     * Récupère un dépôt distant puis lui applique l'audit local ordinaire. Le
     * temporaire est retiré quoi qu'il arrive, y compris si l'analyse échoue.
     */
    private function fetch(Options $options): int
    {
        try {
            $checkout = $this->cloner->fetch(RepositoryUrl::fromString((string) $options->target));
        } catch (GitException $e) {
            $this->stderr($e->getMessage());

            return 2;
        }

        try {
            return $this->audit($checkout->path, $options);
        } finally {
            $this->discard($checkout, $options->keep);
        }
    }

    private function discard(Checkout $checkout, bool $keep): void
    {
        if ($keep) {
            $this->stderr(sprintf('Copie conservée : %s', $checkout->path));

            return;
        }

        $checkout->remove();
    }

    private function audit(string $path, Options $options): int
    {
        $config = $this->loadConfig($path, $options);
        $lenses = LensRegistry::defaults($config);
        $result = (new AuditRunner($lenses, $config))->run($path, $options->qa, $options->coverage);

        try {
            $comparison = $this->compare($result, $options, $config, $lenses);
        } catch (BaselineException $e) {
            $this->stderr($e->getMessage());

            return 2;
        }

        $emitted = $this->emit($result, $comparison, $options, $config, $lenses);

        return 0 === $emitted ? $this->exitCode($result, $comparison, $options, $config, $lenses) : $emitted;
    }

    /**
     * @param list<Lens> $lenses
     *
     * @throws BaselineException
     */
    private function compare(AuditResult $audit, Options $options, Config $config, array $lenses): ?Comparison
    {
        if (null === $options->baselineFile) {
            return null;
        }

        return (new BaselineComparator($lenses, $config))->compare(
            Snapshot::fromFile($options->baselineFile),
            Snapshot::fromAudit($audit, $config, $lenses),
            $options->baselineFile,
        );
    }

    /**
     * Rend le rapport demandé. Renvoie 0, ou 2 si une écriture a échoué.
     *
     * @param list<Lens> $lenses
     */
    private function emit(AuditResult $audit, ?Comparison $comparison, Options $options, Config $config, array $lenses): int
    {
        if ($options->json) {
            $this->stdout((new JsonReporter($lenses, $config))->render($audit, $comparison));

            return 0;
        }

        if ($options->html) {
            // Précédence : --html=FICHIER (CLI) > html.path (config) > stdout.
            $target = $options->htmlTarget ?? $config->htmlPath;

            return $this->emitHtml((new HtmlReporter($lenses, $config))->render($audit, $comparison), $target);
        }

        $this->emitConsole($audit, $comparison, $options, $config, $lenses);

        return 0;
    }

    private function emitHtml(string $html, ?string $target): int
    {
        if (null === $target) {
            $this->stdout($html);

            return 0;
        }

        $error = $this->writeFile($target, $html);
        if (null !== $error) {
            $this->stderr($error);

            return 2;
        }

        $this->stderr(sprintf('Rapport HTML écrit : %s', $target));

        return 0;
    }

    /**
     * @return string|null message d'erreur, ou null si l'écriture a réussi
     */
    private function writeFile(string $target, string $contents): ?string
    {
        $directory = \dirname($target);
        if (!is_dir($directory) && !@mkdir($directory, 0o777, true) && !is_dir($directory)) {
            return sprintf('Répertoire de sortie introuvable et non créable : %s', $directory);
        }

        if (false === @file_put_contents($target, $contents)) {
            return sprintf('Écriture impossible : %s', $target);
        }

        return null;
    }

    /**
     * @param list<Lens> $lenses
     */
    private function emitConsole(AuditResult $audit, ?Comparison $comparison, Options $options, Config $config, array $lenses): void
    {
        $this->stdout((new ConsoleReporter($lenses, $config))->render($audit, $options->showDivergence));

        if (null !== $comparison) {
            $this->stdout("\n" . (new DeltaReporter())->render($comparison));
        }

        if ([] !== $audit->qaResults) {
            $this->stdout("\n" . (new QaReporter())->render($audit->qaResults));
        }

        if (null !== $audit->coverage && null !== $audit->presence) {
            $this->stdout("\n" . (new CoverageReporter())->render($audit->coverage, $audit->presence));
        }

        foreach ($audit->parseErrors as $error) {
            $this->stderr('parse: ' . $error);
        }
    }

    /**
     * Mode gate : 1 dès qu'un seuil est dépassé ou qu'un outil QA requis manque.
     *
     * @param list<Lens> $lenses
     */
    private function exitCode(AuditResult $audit, ?Comparison $comparison, Options $options, Config $config, array $lenses): int
    {
        // Cliquet : seules les régressions échouent, l'existant hérité passe.
        if ($options->failOnNew && null !== $comparison && $comparison->regressionCount() > 0) {
            return 1;
        }

        if (!$options->failOnViolations) {
            return 0;
        }

        $violations = $this->countViolations($audit, $lenses, $config)
            + count((new QaReporter())->missingRequired($audit->qaResults));

        return $violations > 0 ? 1 : 0;
    }

    /**
     * @param list<Lens> $lenses
     */
    private function countViolations(AuditResult $audit, array $lenses, Config $config): int
    {
        $count = 0;
        foreach ($audit->results as $result) {
            foreach ($lenses as $lens) {
                if ($result->metric($lens->key()) > $config->threshold($lens->key())) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    private function loadConfig(string $path, Options $options): Config
    {
        $config = Config::defaults();

        $file = $options->configFile ?? $this->autoDetectConfig($path);
        if (null !== $file && is_file($file)) {
            $config = $config->withOverrides($this->decodeConfigFile($file));
        }

        $overrides = [];
        if (null !== $options->top) {
            $overrides['top'] = $options->top;
        }
        if ([] !== $options->exclude) {
            $overrides['exclude'] = array_merge($config->exclude, $options->exclude);
        }

        return [] === $overrides ? $config : $config->withOverrides($overrides);
    }

    /**
     * Chemin audité : l'argument s'il est fourni, sinon le répertoire courant.
     */
    private function resolvePath(Options $options): string
    {
        if (null !== $options->target) {
            return $options->target;
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

    private function usage(): string
    {
        $version = self::VERSION;

        return <<<TXT
            phpx-complexity {$version} — auditeur de complexité PHP multi-lentilles

            USAGE
              phpx-complexity [CHEMIN] [options]
              phpx-complexity fetch URL [options]

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
              --baseline=FICHIER     Compare à un instantané figé (une sortie --json).
                                     Affiche nouvelles violations, aggravées, résolues
              --fail-on-new          Code de sortie 1 sur les seules RÉGRESSIONS par
                                     rapport à la baseline : l'existant hérité passe
              --fail-on-violations   Code de sortie 1 si un seuil est dépassé, ou si un
                                     outil QA requis manque (mode gate)
              --top=N                Nombre de lignes du classement
              --exclude=FRAGMENT     Exclut les chemins contenant FRAGMENT (répétable)
              --no-divergence        Masque le rapport de divergence
              --keep                 (fetch) Conserve la copie temporaire du dépôt
              -h, --help             Cette aide

            SOUS-COMMANDE FETCH
              Clone un dépôt distant en superficiel dans un dossier temporaire, lui
              applique l'audit, puis nettoie. Schémas acceptés : https://, ssh:// et
              git@hote:chemin. C'est la SEULE partie de l'outil qui accède au réseau ;
              l'analyse, elle, ne voit jamais qu'un chemin local. L'authentification
              est celle de git (agent SSH, credential helper) — aucune invite n'est
              posée, un dépôt privé inaccessible échoue immédiatement.

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
