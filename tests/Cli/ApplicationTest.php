<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Cli;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Cli\Application;
use PhpxComplexity\Tests\Support\BufferedOutput;
use PhpxComplexity\Vcs\Checkout;
use PhpxComplexity\Vcs\GitCloner;

/**
 * Comportement du CLI vérifié en mémoire. Les tests de bout en bout qui lancent
 * réellement le binaire restent dans BaselineGateTest et FetchCommandTest : ils
 * valident le câblage (autoloader, propagation du code de sortie), pas la
 * logique.
 */
final class ApplicationTest extends TestCase
{
    private const PROJECT = __DIR__ . '/../fixtures/baseline-project';
    private const STRICT = self::PROJECT . '/strict.json';

    private BufferedOutput $output;

    private string $temporary;

    protected function setUp(): void
    {
        $this->output = new BufferedOutput();
        $this->temporary = __DIR__ . '/../../var/app-' . bin2hex(random_bytes(4));
        mkdir($this->temporary, 0o755, true);
    }

    protected function tearDown(): void
    {
        (new Checkout($this->temporary, $this->temporary))->remove();
    }

    public function testHelpIsPrintedAndSucceeds(): void
    {
        self::assertSame(0, $this->cli(['--help']));
        self::assertStringContainsString('USAGE', $this->output->out);
        self::assertStringContainsString('SOUS-COMMANDE FETCH', $this->output->out);
    }

    public function testUnknownOptionIsRefusedRatherThanIgnored(): void
    {
        self::assertSame(2, $this->cli(['--jsno', self::PROJECT]));
        self::assertStringContainsString('Option inconnue : --jsno', $this->output->err);
    }

    public function testMissingPathIsReported(): void
    {
        self::assertSame(2, $this->cli([__DIR__ . '/nexiste-pas']));
        self::assertStringContainsString('Chemin introuvable', $this->output->err);
    }

    public function testFailOnNewWithoutBaselineIsRefused(): void
    {
        self::assertSame(2, $this->cli([self::PROJECT, '--fail-on-new']));
        self::assertStringContainsString('--baseline', $this->output->err);
    }

    public function testConsoleReportIsTheDefault(): void
    {
        self::assertSame(0, $this->cli([self::PROJECT]));
        self::assertStringContainsString('phpx-complexity —', $this->output->out);
        self::assertStringContainsString('src/Sample.php::compute', $this->output->out);
    }

    public function testJsonReportIsValidAndCarriesTheSummary(): void
    {
        self::assertSame(0, $this->cli([self::PROJECT, '--json']));

        $payload = json_decode($this->output->out, true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('phpx-complexity', $payload['tool']);
        self::assertSame(1, $payload['summary']['methods']);
    }

    public function testHtmlGoesToStandardOutputWithoutATarget(): void
    {
        self::assertSame(0, $this->cli([self::PROJECT, '--html']));
        self::assertStringStartsWith('<!DOCTYPE html>', $this->output->out);
    }

    public function testHtmlIsWrittenToTheRequestedFile(): void
    {
        $target = $this->temporary . '/sous/dossier/rapport.html';

        self::assertSame(0, $this->cli([self::PROJECT, '--html=' . $target]));
        self::assertFileExists($target, 'le répertoire manquant est créé');
        self::assertStringContainsString('Rapport HTML écrit', $this->output->err);
    }

    public function testUnwritableHtmlTargetIsAnIoError(): void
    {
        $blocker = $this->temporary . '/fichier';
        touch($blocker);

        self::assertSame(2, $this->cli([self::PROJECT, '--html=' . $blocker . '/rapport.html']));
        self::assertStringContainsString('non créable', $this->output->err);
    }

    public function testQaAndCoverageSectionsAreAppendedToTheConsoleReport(): void
    {
        self::assertSame(0, $this->cli([self::PROJECT, '--qa', '--coverage']));
        self::assertStringContainsString('Présence des outils de QA', $this->output->out);
        self::assertStringContainsString("n'est PAS de la couverture", $this->output->out);
    }

    public function testGateFailsOnAnyBreachedThreshold(): void
    {
        self::assertSame(1, $this->cli([self::PROJECT, '--config=' . self::STRICT, '--fail-on-violations']));
        self::assertSame(0, $this->cli([self::PROJECT, '--fail-on-violations']), 'seuils par défaut : rien ne dépasse');
    }

    public function testRatchetAcceptsInheritedViolationsButRefusesNewOnes(): void
    {
        self::assertSame(0, $this->runRatchet('matching-baseline.json'));
        self::assertStringContainsString('0 régression(s)', $this->output->out);

        $this->output = new BufferedOutput();
        self::assertSame(1, $this->runRatchet('empty-baseline.json'));
        self::assertStringContainsString('Nouvelles violations (1)', $this->output->out);
    }

    public function testBaselineOutFreezesASnapshotUsableAsAReference(): void
    {
        $snapshot = $this->temporary . '/instantane.json';

        self::assertSame(0, $this->cli([self::PROJECT, '--config=' . self::STRICT, '--baseline-out=' . $snapshot]));
        self::assertFileExists($snapshot);
        self::assertStringContainsString('Instantané écrit', $this->output->err);
        // Le rapport habituel a tout de même lieu : c'est un artefact, pas un
        // format de sortie.
        self::assertStringContainsString('phpx-complexity —', $this->output->out);

        // Et il est immédiatement exploitable comme référence : rien n'a bougé.
        $this->output = new BufferedOutput();
        self::assertSame(0, $this->cli([
            self::PROJECT,
            '--config=' . self::STRICT,
            '--baseline=' . $snapshot,
            '--fail-on-new',
        ]));
        self::assertStringContainsString('0 régression(s)', $this->output->out);
    }

    public function testUnwritableSnapshotTargetIsAnIoError(): void
    {
        $blocker = $this->temporary . '/bloque';
        touch($blocker);

        self::assertSame(2, $this->cli([self::PROJECT, '--baseline-out=' . $blocker . '/x.json']));
        self::assertStringContainsString('non créable', $this->output->err);
    }

    /**
     * Rétrocompatibilité : une sortie --json complète reste une référence
     * valide, le format d'instantané n'étant qu'un sous-ensemble.
     */
    public function testFullJsonOutputRemainsAValidBaseline(): void
    {
        $full = $this->temporary . '/plein.json';
        self::assertSame(0, $this->cli([self::PROJECT, '--config=' . self::STRICT, '--json']));
        file_put_contents($full, $this->output->out);

        $this->output = new BufferedOutput();
        self::assertSame(0, $this->cli([
            self::PROJECT,
            '--config=' . self::STRICT,
            '--baseline=' . $full,
            '--fail-on-new',
        ]));
        self::assertStringContainsString('0 régression(s)', $this->output->out);
    }

    public function testMalformedBaselineIsAnIoError(): void
    {
        self::assertSame(2, $this->runRatchet('broken-baseline.json'));
        self::assertStringContainsString('Baseline invalide', $this->output->err);
    }

    public function testTopAndExcludeNarrowTheReport(): void
    {
        self::assertSame(0, $this->cli([self::PROJECT, '--top=1', '--exclude=/src/', '--no-divergence']));
        self::assertStringContainsString('0 méthodes', $this->output->out);
        self::assertStringNotContainsString('Divergence', $this->output->out);
    }

    public function testFetchAnalysesTheClonedCopyThenCleansUp(): void
    {
        $cloner = new GitCloner(__DIR__ . '/../fixtures/bin/git', $this->temporary);

        self::assertSame(0, $this->cli(['fetch', 'https://example.org/projet.git'], $cloner));
        self::assertStringContainsString('src/Cloned.php::run', $this->output->out);
        self::assertSame([], array_values(array_diff((array) scandir($this->temporary), ['.', '..'])));
    }

    public function testRejectedUrlIsAUsageError(): void
    {
        self::assertSame(2, $this->cli(['fetch', 'ext::sh -c whoami']));
        self::assertStringContainsString('URL de dépôt non supportée', $this->output->err);
    }

    private function runRatchet(string $baseline): int
    {
        return $this->cli([
            self::PROJECT,
            '--config=' . self::STRICT,
            '--baseline=' . self::PROJECT . '/' . $baseline,
            '--fail-on-new',
        ]);
    }

    /**
     * @param list<string> $args
     */
    private function cli(array $args, ?GitCloner $cloner = null): int
    {
        return (new Application($cloner ?? new GitCloner(), $this->output))
            ->run(['bin/phpx-complexity', ...$args]);
    }
}
