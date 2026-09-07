<?php

declare(strict_types=1);

namespace PhpxComplexity\Coverage;

use FilesystemIterator;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpxComplexity\Support\ProjectRoot;
use PhpxComplexity\Support\RelativePath;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * A STATIC proxy for test presence. It scans the whole project (root resolved
 * composer.json) pour distinguer classes source et classes de test, puis associe
 * each concrete class to a possible `<Name>Test` class (the convention of
 * nommage). Ne mesure pas la couverture — voir CoverageReportReader pour cela.
 */
final class TestPresenceAnalyzer
{
    private const EXCLUDED = ['/vendor/', '/node_modules/', '/.git/', '/var/'];

    private readonly Parser $parser;
    private readonly NodeFinder $finder;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->finder = new NodeFinder();
    }

    public function analyze(string $path): TestPresence
    {
        $root = ProjectRoot::resolve($path);

        $sourceClasses = [];
        $testBaseNames = [];
        $testClasses = 0;
        $testMethods = 0;

        $normalizedRoot = str_replace('\\', '/', $root);
        foreach ($this->phpFiles($root) as $file) {
            $relative = substr(str_replace('\\', '/', $file), strlen($normalizedRoot));
            foreach ($this->classesIn($file) as $class) {
                if ($this->isTestClass($class, $relative)) {
                    ++$testClasses;
                    $testMethods += $this->countTestMethods($class);
                    $testBaseNames[$this->strippedTestName((string) $class->name)] = true;
                } elseif ($this->isConcreteSource($class)) {
                    $sourceClasses[(string) $class->name] = true;
                }
            }
        }

        $untested = array_values(array_filter(
            array_keys($sourceClasses),
            static fn (string $name): bool => !isset($testBaseNames[$name]),
        ));
        sort($untested);

        return new TestPresence(count($sourceClasses), $testClasses, $testMethods, $untested);
    }

    /**
     * @return list<Node\Stmt\Class_>
     */
    private function classesIn(string $file): array
    {
        try {
            $ast = $this->parser->parse((string) file_get_contents($file));
        } catch (Throwable) {
            return [];
        }
        if (null === $ast) {
            return [];
        }

        /** @var list<Node\Stmt\Class_> $classes */
        $classes = $this->finder->find($ast, static fn (Node $n): bool => $n instanceof Node\Stmt\Class_ && null !== $n->name);

        return $classes;
    }

    /**
     * @param string $relativePath path relative to the project root (avoids
     *                             mistaking an ancestor `tests/` directory for
     *                             the project's own test directory)
     */
    private function isTestClass(Node\Stmt\Class_ $class, string $relativePath): bool
    {
        $name = (string) $class->name;
        $extends = null !== $class->extends ? $class->extends->getLast() : '';
        $path = strtolower($relativePath);

        return str_ends_with($name, 'Test')
            || str_contains($extends, 'TestCase')
            || str_contains($path, '/tests/')
            || str_starts_with($path, 'tests/');
    }

    private function isConcreteSource(Node\Stmt\Class_ $class): bool
    {
        return !$class->isAbstract() && !$class->isAnonymous();
    }

    private function countTestMethods(Node\Stmt\Class_ $class): int
    {
        $count = 0;
        foreach ($class->getMethods() as $method) {
            if ($method->isPublic() && (str_starts_with(strtolower((string) $method->name), 'test') || $this->hasTestAttribute($method))) {
                ++$count;
            }
        }

        return $count;
    }

    private function hasTestAttribute(Node\Stmt\ClassMethod $method): bool
    {
        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ('Test' === $attr->name->getLast()) {
                    return true;
                }
            }
        }

        return false;
    }

    private function strippedTestName(string $name): string
    {
        return str_ends_with($name, 'Test') ? substr($name, 0, -4) : $name;
    }

    /**
     * @return iterable<string>
     */
    private function phpFiles(string $root): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || 'php' !== strtolower($file->getExtension())) {
                continue;
            }
            if (!$this->isExcluded(RelativePath::from($root, $file->getPathname()))) {
                yield $file->getPathname();
            }
        }
    }

    /**
     * As for the config: fragments are compared to the path relative to the root.
     */
    private function isExcluded(string $relativePath): bool
    {
        $normalized = '/' . ltrim($relativePath, '/');
        foreach (self::EXCLUDED as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
