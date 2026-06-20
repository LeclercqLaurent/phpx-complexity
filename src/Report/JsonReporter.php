<?php

declare(strict_types=1);

namespace PhpxComplexity\Report;

use PhpxComplexity\Analyzer\MethodResult;
use PhpxComplexity\Config\Config;
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
    public function render(array $results, int $files, array $parseErrors, array $qaResults = []): string
    {
        usort($results, static fn (MethodResult $a, MethodResult $b) => $b->divergence <=> $a->divergence);

        $lenses = [];
        foreach ($this->lenses as $lens) {
            $lenses[$lens->key()] = [
                'label' => $lens->label(),
                'reference' => $lens->reference(),
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

        $payload = [
            'tool' => 'phpx-complexity',
            'summary' => [
                'files' => $files,
                'methods' => count($results),
                'parseErrors' => $parseErrors,
            ],
            'lenses' => $lenses,
            'methods' => $methods,
        ];

        if ([] !== $qaResults) {
            $payload['qa'] = $this->qaPayload($qaResults);
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
        foreach ($qaResults as $result) {
            $tools[$result->tool->key] = [
                'label' => $result->tool->label,
                'category' => $result->tool->category,
                'present' => $result->present,
                'required' => $result->required,
                'evidence' => $result->evidence,
            ];
            if ($result->required && !$result->present) {
                $missingRequired[] = $result->tool->key;
            }
        }

        return ['tools' => $tools, 'missingRequired' => $missingRequired];
    }
}
