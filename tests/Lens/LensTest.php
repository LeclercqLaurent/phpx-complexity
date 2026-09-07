<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Lens;

use PhpParser\Node\Stmt\Function_;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use PhpxComplexity\Lens\CognitiveComplexityLens;
use PhpxComplexity\Lens\EntanglementLens;
use PhpxComplexity\Lens\Lens;
use PhpxComplexity\Lens\LiveVariablePeakLens;
use PhpxComplexity\Lens\ParameterCountLens;
use PhpxComplexity\Lens\ReturnCountLens;

final class LensTest extends TestCase
{
    public function testParameterCount(): void
    {
        self::assertSame(3.0, $this->measure(new ParameterCountLens(), 'function f($a, $b, $c) {}'));
    }

    public function testEachLensExposesANonEmptyDescription(): void
    {
        $lenses = [
            new CognitiveComplexityLens(),
            new ParameterCountLens(),
            new ReturnCountLens(),
            new LiveVariablePeakLens(),
            new EntanglementLens(),
        ];
        foreach ($lenses as $lens) {
            self::assertNotSame('', trim($lens->description()), $lens->key() . ' must define what it measures');
        }
    }

    public function testReturnCountIgnoresNestedClosures(): void
    {
        $code = 'function f($x) {
            $g = function () { return 1; };
            if ($x) { return 2; }
            return 3;
        }';
        self::assertSame(2.0, $this->measure(new ReturnCountLens(), $code));
    }

    public function testCognitiveComplexityWithNestingAndLogicalOperators(): void
    {
        // if (+1), && (+1), nested foreach (+2: +1 structural, +1 nesting)
        // = 4 au total.
        $code = 'function f($a, $b, $items) {
            if ($a && $b) {
                foreach ($items as $i) {
                    echo $i;
                }
            }
        }';
        self::assertSame(4.0, $this->measure(new CognitiveComplexityLens(), $code));
    }

    public function testFlatAssignmentsAreNotEntangled(): void
    {
        // $this->x = $x; interweaves nothing, so the average degree is 0.
        $code = 'function f($a, $b, $c) {
            $this->a = $a;
            $this->b = $b;
            $this->c = $c;
        }';
        self::assertSame(0.0, $this->measure(new EntanglementLens(), $code));
    }

    public function testCombinedVariablesAreEntangled(): void
    {
        // $c = $a + $b: a-b through the "+", c-a and c-b through the assignment
        // flow, hence a triangle (3 vertices, 3 edges) and an average degree of
        // 2*3/3 = 2.0.
        $code = 'function f($a, $b) {
            $c = $a + $b;
            return $c;
        }';
        self::assertSame(2.0, $this->measure(new EntanglementLens(), $code));
    }

    public function testLiveVariablePeak(): void
    {
        $code = 'function f() {
            $a = 1;
            $b = $a;
            $c = $b;
            return $c;
        }';
        self::assertGreaterThanOrEqual(2.0, $this->measure(new LiveVariablePeakLens(), $code));
    }

    private function measure(Lens $lens, string $code): float
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse('<?php ' . $code);
        self::assertNotNull($ast);
        $function = $ast[0];
        self::assertInstanceOf(Function_::class, $function);

        return $lens->measure($function, (array) $function->getStmts());
    }
}
