<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Lens;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PhpxComplexity\Lens\CognitiveComplexityLens;

/**
 * Conformance with S3776.
 *
 * Les valeurs attendues viennent de la SPÉCIFICATION (white paper SonarSource
 * "Cognitive Complexity"), not from the output of our implementation, which is
 * the only way for this test to catch a faulty reimplementation. The README
 * claims a native reproduction of the rule, and this suite is what makes the
 * claim verifiable.
 */
final class CognitiveComplexityConformanceTest extends TestCase
{
    /**
     * @return list<array{0:string,1:int,2:string}>
     */
    public static function cases(): array
    {
        return [
            ['noBranching', 0, 'no control structure at all'],
            ['sumOfPrimes', 7, 'canonical example: loop 1 + nested loop 2 + if 3 + jump 1'],
            ['getWords', 1, 'canonical example: a switch costs 1, whatever the number of cases'],
            ['withElseif', 3, 'if 1 + elseif 1 + else 1'],
            ['withElseSpaceIf', 3, '"else if" counts as "elseif"'],
            ['elseAvoidsNestingPenalty', 4, 'if 1 + nested if 2 + else 1, with no penalty on the else'],
            ['nestedConditions', 6, 'boucle 1 + if 2 + boucle 3'],
            ['singleSequence', 2, 'if 1 + one single && sequence'],
            ['mixedSequences', 3, 'if 1 + && sequence 1 + || sequence 1'],
            ['negationIsFree', 2, 'if 1 + && sequence 1; negation is free'],
            ['ternary', 1, 'a ternary costs 1'],
            ['nestedTernary', 3, 'ternary 1 + nested ternary 2'],
            ['catchIncrements', 2, 'two catches, the try itself costs nothing'],
            ['catchNesting', 3, 'boucle 1 + catch 2 ; continue sans niveau est gratuit'],
            ['closureAddsNestingOnly', 2, 'the closure costs nothing but nests the if to 2'],
            ['simpleBreakIsFree', 3, 'boucle 1 + if 2 ; break simple gratuit'],
            ['labelledBreakCounts', 7, 'boucle 1 + boucle 2 + if 3 + break 2 → 1'],
            ['gotoCounts', 2, 'if 1 + goto 1'],
            ['matchLikeSwitch', 1, 'accepted deviation: match treated as a switch'],
        ];
    }

    #[DataProvider('cases')]
    public function testMatchesTheSpecification(string $method, int $expected, string $rationale): void
    {
        self::assertSame($expected, $this->measure($method), sprintf('%s — %s', $method, $rationale));
    }

    public function testEveryFixtureMethodIsCovered(): void
    {
        $declared = array_map(
            static fn (Node\Stmt\ClassMethod $m): string => (string) $m->name,
            $this->methods(),
        );
        $asserted = array_map(static fn (array $case): string => $case[0], self::cases());

        // "run" is nothing but a container for the try blocks.
        self::assertSame([], array_values(array_diff($declared, $asserted, ['run'])));
    }

    private function measure(string $method): int
    {
        foreach ($this->methods() as $node) {
            if ((string) $node->name === $method) {
                return (int) (new CognitiveComplexityLens())->measure($node, $node->stmts ?? []);
            }
        }

        self::fail(sprintf('Method not found in the fixture: %s', $method));
    }

    /**
     * @return list<Node\Stmt\ClassMethod>
     */
    private function methods(): array
    {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/s3776/Conformance.php');
        $ast = (new ParserFactory())->createForNewestSupportedVersion()->parse($source) ?? [];

        /** @var list<Node\Stmt\ClassMethod> $found */
        $found = (new NodeFinder())->findInstanceOf($ast, Node\Stmt\ClassMethod::class);

        return $found;
    }
}
