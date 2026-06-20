<?php

declare(strict_types=1);

namespace PhpxComplexity\Qa;

/**
 * Vérifie la présence des outils de QA à la racine d'un projet, en croisant les
 * dépendances déclarées dans composer.json et les fichiers de configuration.
 */
final class QaPresenceChecker
{
    /**
     * @param list<QaTool>  $tools
     * @param list<string>  $required clés des outils considérés obligatoires
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
        $root = $this->projectRoot($path);
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
     * Racine du projet : on part du chemin analysé et on remonte jusqu'au premier
     * dossier contenant composer.json (cas courant : on audite `app/src` mais la
     * config QA vit dans `app/`). À défaut, le dossier de départ fait foi.
     */
    private function projectRoot(string $path): string
    {
        $dir = rtrim(str_replace('\\', '/', is_file($path) ? \dirname($path) : $path), '/');
        $current = $dir;
        while ('' !== $current && '/' !== $current) {
            if (is_file($current . '/composer.json')) {
                return $current;
            }
            $current = \dirname($current);
        }

        return $dir;
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
