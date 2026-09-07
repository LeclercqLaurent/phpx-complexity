<?php

declare(strict_types=1);

namespace PhpxComplexity\Qa;

use PhpxComplexity\Support\ProjectRoot;

/**
 * Checks which QA tools are present at the root of a project, by cross-checking
 * the dependencies declared in composer.json against the configuration files.
 */
final class QaPresenceChecker
{
    /**
     * @param list<QaTool>  $tools
     * @param list<string>  $required keys of the tools deemed mandatory
     */
    public function __construct(
        private readonly array $tools,
        private readonly array $required,
    ) {
    }

    /**
     * @return list<QaToolResult>
     */
    public function check(string $path): array
    {
        $root = ProjectRoot::resolve($path);
        $packages = $this->composerPackages($root);

        $results = [];
        foreach ($this->tools as $tool) {
            $evidence = [];
            foreach ($tool->packages as $package) {
                if (isset($packages[strtolower($package)])) {
                    $evidence[] = 'composer:' . $package;
                }
            }
            foreach ($tool->files as $file) {
                if (file_exists($root . '/' . $file)) {
                    $evidence[] = $file;
                }
            }
            $results[] = new QaToolResult(
                tool: $tool,
                present: [] !== $evidence,
                evidence: $evidence,
                required: \in_array($tool->key, $this->required, true),
            );
        }

        return $results;
    }

    /**
     * @return array<string,true>
     */
    private function composerPackages(string $root): array
    {
        $file = $root . '/composer.json';
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            return [];
        }

        $packages = [];
        foreach (['require', 'require-dev'] as $section) {
            if (!isset($data[$section]) || !is_array($data[$section])) {
                continue;
            }
            foreach (array_keys($data[$section]) as $name) {
                $packages[strtolower((string) $name)] = true;
            }
        }

        return $packages;
    }
}
