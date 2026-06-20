<?php

declare(strict_types=1);

namespace PhpxComplexity\Analyzer;

use FilesystemIterator;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpxComplexity\Config\Config;
use PhpxComplexity\Lens\Lens;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Orchestre l'analyse : parcourt les fichiers PHP, applique chaque lentille à
 * chaque méthode, puis calcule rangs centiles et divergence à l'échelle du lot.
 */
final class ProjectAnalyzer
{
    private readonly Parser $parser;

    /**
     * @param list<Lens> $lenses
     */
    public function __construct(
        private readonly array $lenses,
        private readonly Config $config,
    ) {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @return array{results: list<MethodResult>, parseErrors: list<string>, files: int}
     */
    public function analyze(string $path): array
    {
        $results = [];
        $parseErrors = [];
        $files = 0;

        foreach ($this->phpFiles($path) as $file) {
            ++$files;
            try {
                $ast = $this->parser->parse((string) file_get_contents($file));
            } catch (Throwable $e) {
                $parseErrors[] = sprintf('%s : %s', $file, $e->getMessage());
                continue;
            }
            if (null === $ast) {
                continue;
            }
            foreach ($this->functions($ast) as $function) {
                $result = $this->measureFunction($file, $path, $function);
                if (null !== $result) {
                    $results[] = $result;
                }
            }
        }

        $this->computePercentilesAndDivergence($results);

        return ['results' => $results, 'parseErrors' => $parseErrors, 'files' => $files];
    }

    private function measureFunction(string $file, string $root, Node\FunctionLike $function): ?MethodResult
    {
        $stmts = $function->getStmts();
        if (null === $stmts) {
            return null;
        }

        $metrics = [];
        foreach ($this->lenses as $lens) {
            $metrics[$lens->key()] = $lens->measure($function, $stmts);
        }

        return new MethodResult(
            file: $this->relativePath($root, $file),
            name: $this->functionName($function),
            line: $function->getStartLine(),
            metrics: $metrics,
        );
    }

    /**
     * @param array<int,Node> $ast
     *
     * @return list<Node\FunctionLike>
     */
    private function functions(array $ast): array
    {
        $visitor = new class extends NodeVisitorAbstract {
            /** @var list<Node\FunctionLike> */
            public array $found = [];

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Node\Stmt\ClassMethod || $node instanceof Node\Stmt\Function_) {
                    $this->found[] = $node;
                }

                return null;
            }
        };
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->found;
    }

    /**
     * Rang centile de chaque lentille, puis divergence = écart entre le rang le
     * plus haut et le plus bas (les lentilles se contredisent → angle mort).
     *
     * @param list<MethodResult> $results
     */
    private function computePercentilesAndDivergence(array $results): void
    {
        if ([] === $results) {
            return;
        }

        foreach ($this->lenses as $lens) {
            $key = $lens->key();
            $values = array_map(static fn (MethodResult $r) => $r->metric($key), $results);
            sort($values);
            $count = count($values);
            foreach ($results as $result) {
                $rank = $this->percentileRank($values, $result->metric($key), $count);
                $result->percentile[$key] = $rank;
            }
        }

        foreach ($results as $result) {
            $ranks = array_values($result->percentile);
            $result->divergence = [] === $ranks ? 0.0 : max($ranks) - min($ranks);
        }
    }

    /**
     * @param list<float> $sortedValues
     */
    private function percentileRank(array $sortedValues, float $value, int $count): float
    {
        $below = 0;
        foreach ($sortedValues as $v) {
            if ($v < $value) {
                ++$below;
            } else {
                break;
            }
        }

        return $count <= 1 ? 0.0 : $below / ($count - 1);
    }

    /**
     * @return iterable<string>
     */
    private function phpFiles(string $path): iterable
    {
        if (is_file($path)) {
            yield $path;

            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || 'php' !== strtolower($file->getExtension())) {
                continue;
            }
            $real = $file->getPathname();
            if (!$this->config->isExcluded($real)) {
                yield $real;
            }
        }
    }

    private function relativePath(string $root, string $file): string
    {
        $root = rtrim(str_replace('\\', '/', is_file($root) ? dirname($root) : $root), '/');
        $file = str_replace('\\', '/', $file);

        return str_starts_with($file, $root . '/') ? substr($file, strlen($root) + 1) : $file;
    }

    private function functionName(Node\FunctionLike $function): string
    {
        if ($function instanceof Node\Stmt\ClassMethod || $function instanceof Node\Stmt\Function_) {
            return (string) $function->name;
        }

        return 'closure';
    }
}
