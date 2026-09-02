<?php

declare(strict_types=1);

namespace PhpxComplexity\Vcs;

use PhpxComplexity\Vcs\Exception\GitException;

/**
 * Récupère un dépôt distant dans un dossier temporaire.
 *
 * SEUL point de l'outil qui accède au réseau. Il est délibérément tenu à
 * l'écart du cœur : il produit un chemin local, et l'analyse ne connaît que ce
 * chemin. La garantie « hors-ligne » de l'analyse elle-même reste donc vraie.
 *
 * Durcissement du clonage : commande passée en tableau (aucun shell, donc
 * aucune interpolation), séparateur « -- » avant l'URL, transport `ext::`
 * interdit (exécution de commande arbitraire), hooks neutralisés, clone
 * superficiel et sans étiquettes, et pas d'invite interactive — un dépôt privé
 * échoue franchement au lieu de faire attendre indéfiniment.
 */
final class GitCloner
{
    private const NOT_FOUND = 127;

    public function __construct(
        private readonly string $binary = 'git',
        private readonly ?string $temporaryBase = null,
    ) {
    }

    /**
     * @throws GitException
     */
    public function fetch(RepositoryUrl $url): Checkout
    {
        $checkout = $this->prepare($url->name());

        [$status, $details] = $this->git([
            '-c', 'protocol.ext.allow=never',
            '-c', 'core.hooksPath=' . $checkout->root . '/hooks',
            'clone', '--depth=1', '--no-tags', '--quiet',
            '--', $url->value, $checkout->path,
        ]);

        if (0 !== $status) {
            $checkout->remove();
            throw self::NOT_FOUND === $status
                ? GitException::gitMissing($this->binary)
                : GitException::cloneFailed($url->value, $details);
        }

        return $checkout;
    }

    private function prepare(string $name): Checkout
    {
        $base = $this->temporaryBase ?? sys_get_temp_dir();
        $root = sprintf('%s/phpx-complexity-%s-%s', rtrim($base, '/'), $name, bin2hex(random_bytes(6)));

        // « hooks » reste vide : core.hooksPath y pointe pour n'exécuter aucun
        // script apporté par le dépôt.
        if (!@mkdir($root . '/hooks', 0o700, true) && !is_dir($root . '/hooks')) {
            throw GitException::temporaryDirectoryFailed($root);
        }

        return new Checkout($root, $root . '/depot');
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{0:int,1:string}
     */
    private function git(array $arguments): array
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $pipes = [];
        $process = @proc_open(
            [$this->binary, ...$arguments],
            $descriptors,
            $pipes,
            null,
            ['GIT_TERMINAL_PROMPT' => '0'] + getenv(),
        );

        if (!is_resource($process)) {
            return [self::NOT_FOUND, ''];
        }

        $details = (string) stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        return [proc_close($process), $details];
    }
}
