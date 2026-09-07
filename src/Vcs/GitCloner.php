<?php

declare(strict_types=1);

namespace PhpxComplexity\Vcs;

use PhpxComplexity\Vcs\Exception\GitException;

/**
 * Fetches a remote repository into a temporary directory.
 *
 * The ONLY point of the tool that touches the network. It is deliberately kept
 * away from the core: it produces a local path, and the analysis knows nothing
 * but that path. The offline guarantee of the analysis itself therefore holds.
 *
 * Hardening of the clone: the command is passed as an array (no shell, hence no
 * interpolation), a "--" separator precedes the URL, the `ext::` transport is
 * forbidden (arbitrary command execution), hooks are neutralised, the clone is
 * shallow and tagless, and no interactive prompt is raised, so a private
 * repository fails outright instead of hanging forever.
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

        // "hooks" stays empty: core.hooksPath points at it so that no script
        // brought by the repository is ever executed.
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
