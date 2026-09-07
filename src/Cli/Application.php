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
 * CLI entry point: resolves the options and the path, delegates the audit, picks
 * a report, computes the exit code. No measurement logic lives here.
 *
 * Exit codes: 0 = OK, 1 = violations (gate mode), 2 = usage or I/O error.
 */
final class Application
{
    public const VERSION = '0.1.0';

    public function __construct(
        private readonly GitCloner $cloner = new GitCloner(),
        private readonly Output $output = new StreamOutput(),
    ) {
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $options = new Options(array_slice($argv, 1));
        if ($options->help) {
            $this->output->write($this->usage());

            return 0;
        }

        $error = $this->usageError($options);
        if (null !== $error) {
            $this->output->error($error);

            return 2;
        }

        return Command::Fetch === $options->command
            ? $this->fetch($options)
            : $this->audit($this->resolvePath($options), $options);
    }

    /**
     * An inconsistent invocation: an error message, or null if it holds up.
     */
    private function usageError(Options $options): ?string
    {
        if ([] !== $options->unknown) {
            return sprintf(
                "Unknown option: %s\nSee 'phpx-complexity --help' for the list.",
                implode(', ', $options->unknown),
            );
        }

        if ($options->failOnNew && null === $options->baselineFile) {
            return '--fail-on-new expects a reference: add --baseline=FILE.';
        }

        return $this->targetError($options);
    }

    /**
     * The expected target differs per command: a repository URL for fetch, an
     * existing path for a local audit.
     */
    private function targetError(Options $options): ?string
    {
        if (Command::Fetch === $options->command) {
            return null === $options->target ? "The 'fetch' subcommand expects a repository URL." : null;
        }

        $path = $this->resolvePath($options);

        return file_exists($path) ? null : sprintf('Path not found: %s', $path);
    }

    /**
     * Fetches a remote repository then runs the ordinary local audit on it. The
     * temporary copy is removed whatever happens, including on analysis failure.
     */
    private function fetch(Options $options): int
    {
        try {
            $checkout = $this->cloner->fetch(RepositoryUrl::fromString((string) $options->target));
        } catch (GitException $e) {
            $this->output->error($e->getMessage());

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
            $this->output->error(sprintf('Copy kept at: %s', $checkout->path));

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
            $this->output->error($e->getMessage());

            return 2;
        }

        $written = $this->writeSnapshot($result, $options, $config, $lenses);
        $emitted = 0 !== $written ? $written : $this->emit($result, $comparison, $options, $config, $lenses);

        return 0 === $emitted ? $this->exitCode($result, $comparison, $options, $config, $lenses) : $emitted;
    }

    /**
     * Freezes the snapshot requested by --baseline-out, alongside the report:
     * this is an artefact rather than an output format, so the usual report is
     * produced all the same.
     *
     * @param list<Lens> $lenses
     */
    private function writeSnapshot(AuditResult $audit, Options $options, Config $config, array $lenses): int
    {
        if (null === $options->baselineOut) {
            return 0;
        }

        $snapshot = Snapshot::fromAudit($audit, $config, $lenses)->toJson();
        $error = $this->writeFile($options->baselineOut, $snapshot);
        if (null !== $error) {
            $this->output->error($error);

            return 2;
        }

        $this->output->error(sprintf('Snapshot written to: %s', $options->baselineOut));

        return 0;
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
     * Renders the requested report. Returns 0, or 2 if a write failed.
     *
     * @param list<Lens> $lenses
     */
    private function emit(AuditResult $audit, ?Comparison $comparison, Options $options, Config $config, array $lenses): int
    {
        if ($options->json) {
            $this->output->write((new JsonReporter($lenses, $config))->render($audit, $comparison));

            return 0;
        }

        if ($options->html) {
            // Precedence: --html=FILE (CLI) > html.path (config) > stdout.
            $target = $options->htmlTarget ?? $config->htmlPath;

            return $this->emitHtml((new HtmlReporter($lenses, $config))->render($audit, $comparison), $target);
        }

        $this->emitConsole($audit, $comparison, $options, $config, $lenses);

        return 0;
    }

    private function emitHtml(string $html, ?string $target): int
    {
        if (null === $target) {
            $this->output->write($html);

            return 0;
        }

        $error = $this->writeFile($target, $html);
        if (null !== $error) {
            $this->output->error($error);

            return 2;
        }

        $this->output->error(sprintf('HTML report written to: %s', $target));

        return 0;
    }

    /**
     * @return string|null an error message, or null when the write succeeded
     */
    private function writeFile(string $target, string $contents): ?string
    {
        $directory = \dirname($target);
        if (!is_dir($directory) && !@mkdir($directory, 0o777, true) && !is_dir($directory)) {
            return sprintf('Output directory is missing and cannot be created: %s', $directory);
        }

        if (false === @file_put_contents($target, $contents)) {
            return sprintf('Cannot write to: %s', $target);
        }

        return null;
    }

    /**
     * @param list<Lens> $lenses
     */
    private function emitConsole(AuditResult $audit, ?Comparison $comparison, Options $options, Config $config, array $lenses): void
    {
        $this->output->write((new ConsoleReporter($lenses, $config))->render($audit, $options->showDivergence));

        if (null !== $comparison) {
            $this->output->write("\n" . (new DeltaReporter())->render($comparison));
        }

        if ([] !== $audit->qaResults) {
            $this->output->write("\n" . (new QaReporter())->render($audit->qaResults));
        }

        if (null !== $audit->coverage && null !== $audit->presence) {
            $this->output->write("\n" . (new CoverageReporter())->render($audit->coverage, $audit->presence));
        }

        foreach ($audit->parseErrors as $error) {
            $this->output->error('parse: ' . $error);
        }
    }

    /**
     * Gate mode: 1 as soon as a threshold is crossed or a required QA tool is
     * missing.
     *
     * @param list<Lens> $lenses
     */
    private function exitCode(AuditResult $audit, ?Comparison $comparison, Options $options, Config $config, array $lenses): int
    {
        // Ratchet: only regressions fail, inherited debt passes.
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
     * The audited path: the argument when given, otherwise the current directory.
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
     * The JSON content of the config file, narrowed to string keys: a top-level
     * JSON array is not a valid configuration.
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
            phpx-complexity {$version}, a multi-lens PHP complexity auditor

            USAGE
              phpx-complexity [PATH] [options]
              phpx-complexity fetch URL [options]

            LENSES
              cognitive  S3776 cognitive complexity (branching + nesting)
              params     S107  parameter count
              returns    S1142 number of return statements
              live_peak  peak of live variables (working memory)
              entangle   data entanglement (degree of the co-occurrence graph)

            OPTIONS
              --json                 JSON output (CI, dashboards)
              --html[=FILE]          Standalone HTML report (offline). With no value:
                                     standard output; with =FILE: writes the file
              --qa                   Checks that the project's QA tools are present
              --coverage             Reads a coverage report (clover/cobertura) when one
                                     exists, plus static facts about test presence
              --config=FILE          Config file (default: phpx-complexity.json)
              --baseline=FILE        Compares against a frozen snapshot. Reports new,
                                     worsened and resolved violations
              --baseline-out=FILE    Freezes the reference snapshot. Narrowed to what
                                     the comparison reads and sorted by identity, so
                                     regenerating it stays reviewable
              --fail-on-new          Exit code 1 on REGRESSIONS against the baseline
                                     only: inherited debt passes
              --fail-on-violations   Exit code 1 when a threshold is crossed, or when a
                                     required QA tool is missing (gate mode)
              --top=N                Number of rows in the ranking
              --exclude=FRAGMENT     Excludes paths containing FRAGMENT (repeatable)
              --no-divergence        Hides the divergence report
              --keep                 (fetch) Keeps the temporary copy of the repository
              -h, --help             This help

            FETCH SUBCOMMAND
              Shallow-clones a remote repository into a temporary directory, audits it,
              then cleans up. Accepted schemes: https://, ssh:// and git@host:path. This
              is the ONLY part of the tool that touches the network; the analysis itself
              never sees anything but a local path. Authentication is git's own (SSH
              agent, credential helper) and no prompt is ever raised, so an unreachable
              private repository fails immediately.

            The DIVERGENCE report highlights the methods where the lenses contradict
            each other, which is the blind spot of any single complexity metric.

            TXT;
    }
}
