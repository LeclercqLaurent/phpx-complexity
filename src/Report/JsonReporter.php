<?php

declare(strict_types=1);

namespace PhpxComplexity\Report;

use PhpxComplexity\Analyzer\MethodResult;
use PhpxComplexity\Config\Config;
use PhpxComplexity\Coverage\CoverageReport;
use PhpxComplexity\Coverage\TestPresence;
use PhpxComplexity\Lens\Lens;
use PhpxComplexity\Qa\QaToolResult;

/**
 * Export JSON pour CI, dashboards et diff entre deux runs.
 */
final class JsonReporter
{
    /**
     * @param list<Lens> $lenses
     */
    public function __construct(
        private readonly array $lenses,
        private readonly Config $config,
    ) {
    }

    /**
     * @param list<MethodResult> $results
     * @param list<string>       $parseErrors
     * @param list<QaToolResult> $qaResults
     */
    public function render(array $results, int $files, array $parseErrors, array $qaResults = [], ?CoverageReport $coverage = null, ?TestPresence $presence = null): string
    {
        usort($results, static fn (MethodResult $a, MethodResult $b) => $b->divergence <=> $a->divergence);

        $lenses = [];
        foreach ($this->lenses as $lens) {
            $lenses[$lens->key()] = [
                'label' => $lens->label(),
                'reference' => $lens->reference(),
                'description' => $lens->description(),
                'threshold' => $this->config->threshold($lens->key()),
            ];
        }

        $methods = [];
        foreach ($results as $r) {
            $violations = [];
            foreach ($this->lenses as $lens) {
                if ($r->metric($lens->key()) > $this->config->threshold($lens->key())) {
                    $violations[] = $lens->key();
                }
            }
            $methods[] = [
                'file' => $r->file,
                'name' => $r->name,
                'line' => $r->line,
                'metrics' => $r->metrics,
                'percentile' => $r->percentile,
                'divergence' => round($r->divergence, 4),
                'violations' => $violations,
            ];
        }

        $methodsInViolation = 0;
        $totalViolations = 0;
        foreach ($results as $r) {
            $count = $this->violationCount($r);
            $totalViolations += $count;
            $methodsInViolation += $count > 0 ? 1 : 0;
        }

        $payload = [
            'tool' => 'phpx-complexity',
            'summary' => [
                'files' => $files,
                'methods' => count($results),
                'methodsInViolation' => $methodsInViolation,
                'totalViolations' => $totalViolations,
                'parseErrors' => $parseErrors,
            ],
            'lenses' => $lenses,
            'methods' => $methods,
        ];

        if ([] !== $qaResults) {
            $payload['qa'] = $this->qaPayload($qaResults);
        }

        if (null !== $coverage || null !== $presence) {
            $payload['coverage'] = $this->coveragePayload($coverage, $presence);
        }

        return (string) json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param list<QaToolResult> $qaResults
     *
     * @return array<string,mixed>
     */
    private function qaPayload(array $qaResults): array
    {
        $tools = [];
        $missingRequired = [];
        $categories = [];
        foreach ($qaResults as $result) {
            $tools[$result->tool->key] = [
                'label' => $result->tool->label,
                'category' => $result->tool->category,
                'present' => $result->present,
                'required' => $result->required,
                'evidence' => $result->evidence,
            ];
            $category = $result->tool->category;
            $categories[$category] = ($categories[$category] ?? false) || $result->present;
            if ($result->required && !$result->present) {
                $missingRequired[] = $result->tool->key;
            }
        }

        return [
            'tools' => $tools,
            'toolsPresent' => count(array_filter($tools, static fn (array $t) => $t['present'])),
            'toolsTotal' => count($tools),
            'categoriesCovered' => count(array_filter($categories)),
            'categoriesTotal' => count($categories),
            'missingRequired' => $missingRequired,
        ];
    }

    private function violationCount(MethodResult $r): int
    {
        $count = 0;
        foreach ($this->lenses as $lens) {
            if ($r->metric($lens->key()) > $this->config->threshold($lens->key())) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @return array<string,mixed>
     */
    private function coveragePayload(?CoverageReport $coverage, ?TestPresence $presence): array
    {
        $payload = [];

        if (null !== $coverage) {
            $payload['report'] = [
                'measured' => $coverage->found,
                'format' => $coverage->format,
                'source' => $coverage->source,
                'linePercent' => $coverage->linePercent,
                'linesCovered' => $coverage->linesCovered,
                'linesValid' => $coverage->linesValid,
                'methodPercent' => $coverage->methodPercent,
            ];
        }

        if (null !== $presence) {
            $payload['testPresence'] = [
                'sourceClasses' => $presence->sourceClasses,
                'testClasses' => $presence->testClasses,
                'testMethods' => $presence->testMethods,
                'classesWithTest' => $presence->testedClasses(),
                'untestedClasses' => $presence->untestedClasses,
            ];
        }

        return $payload;
    }
}
