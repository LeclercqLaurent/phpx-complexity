<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Report;

use PHPUnit\Framework\TestCase;
use PhpxComplexity\Analyzer\MethodResult;
use PhpxComplexity\Audit\AuditResult;
use PhpxComplexity\Config\Config;
use PhpxComplexity\Lens\CognitiveComplexityLens;
use PhpxComplexity\Lens\EntanglementLens;
use PhpxComplexity\Lens\Lens;
use PhpxComplexity\Lens\LiveVariablePeakLens;
use PhpxComplexity\Lens\ParameterCountLens;
use PhpxComplexity\Lens\ReturnCountLens;
use PhpxComplexity\Report\HtmlReporter;

final class HtmlReporterTest extends TestCase
{
    public function testRendersSelfContainedDocumentWithSections(): void
    {
        $html = $this->render($this->sampleResults());

        self::assertStringStartsWith('<!DOCTYPE html>', $html);
        self::assertStringContainsString('<table id="methods">', $html);
        self::assertStringContainsString('id="pairs"', $html);
        self::assertStringContainsString('<h2>Lenses</h2>', $html);
        // Every KPI is defined in the legend.
        self::assertStringContainsString('ldesc', $html);
        self::assertStringContainsString('mental effort', $html);
        self::assertStringContainsString('working-memory load', $html);
        // Factual data is present, with no notion of a score.
        self::assertStringNotContainsStringIgnoringCase('score', $html);
    }

    public function testNoExternalResources(): void
    {
        $html = $this->render($this->sampleResults());

        // No network resource: no http(s) beyond the SVG namespace URI,
        // ni src= externe.
        $withoutSvgNs = str_replace('http://www.w3.org/2000/svg', '', $html);
        self::assertStringNotContainsString('http://', $withoutSvgNs);
        self::assertStringNotContainsString('https://', $withoutSvgNs);
        self::assertStringNotContainsString('src=', $html);

        // Les seuls href sont ceux du menu, qui pointent vers des ancres internes.
        preg_match_all('/href="([^"]*)"/', $html, $hrefs);
        self::assertNotSame([], $hrefs[1], 'le menu doit exister');
        foreach ($hrefs[1] as $href) {
            self::assertStringStartsWith('#', $href, 'aucun href ne sort du document');
        }
    }

    public function testEscapesCodeDerivedContent(): void
    {
        $evil = new MethodResult(
            file: 'src/<script>alert(1)</script>.php',
            name: 'pwn',
            line: 1,
            metrics: ['cognitive' => 1.0, 'params' => 0.0, 'returns' => 0.0, 'live_peak' => 0.0, 'entangle' => 0.0],
            percentile: ['cognitive' => 1.0, 'params' => 0.0, 'returns' => 0.0, 'live_peak' => 0.0, 'entangle' => 0.0],
            divergence: 1.0,
        );

        $html = $this->render([$evil]);

        // Injected code must never appear verbatim: neither in the server HTML
        // (escaped to &lt;) nor in the embedded JSON (escaped to <).
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        // Exactly two legitimate </script> tags: closing the data block and the
        // JS block. A third would signal a context escape.
        self::assertSame(2, substr_count($html, '</script>'));
    }

    /**
     * @param list<MethodResult> $results
     */
    private function render(array $results): string
    {
        return (new HtmlReporter($this->lenses(), Config::defaults()))
            ->render(new AuditResult($results, files: 3, parseErrors: []));
    }

    /**
     * @return list<MethodResult>
     */
    private function sampleResults(): array
    {
        return [
            new MethodResult(
                file: 'src/Foo.php',
                name: 'compute',
                line: 12,
                metrics: ['cognitive' => 18.0, 'params' => 1.0, 'returns' => 1.0, 'live_peak' => 9.0, 'entangle' => 2.5],
                percentile: ['cognitive' => 1.0, 'params' => 0.2, 'returns' => 0.2, 'live_peak' => 0.9, 'entangle' => 0.6],
                divergence: 0.8,
            ),
            new MethodResult(
                file: 'src/Bar.php',
                name: 'restore',
                line: 4,
                metrics: ['cognitive' => 0.0, 'params' => 8.0, 'returns' => 1.0, 'live_peak' => 8.0, 'entangle' => 0.0],
                percentile: ['cognitive' => 0.0, 'params' => 1.0, 'returns' => 0.2, 'live_peak' => 0.8, 'entangle' => 0.0],
                divergence: 1.0,
            ),
        ];
    }

    /**
     * @return list<Lens>
     */
    private function lenses(): array
    {
        return [
            new CognitiveComplexityLens(),
            new ParameterCountLens(),
            new ReturnCountLens(),
            new LiveVariablePeakLens(),
            new EntanglementLens(),
        ];
    }
}
