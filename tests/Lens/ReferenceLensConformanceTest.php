<?php

declare(strict_types=1);

namespace PhpxComplexity\Tests\Lens;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PhpxComplexity\Lens\Lens;
use PhpxComplexity\Lens\ParameterCountLens;
use PhpxComplexity\Lens\ReturnCountLens;

/**
 * Conformance of the two other reproduced SonarQube rules: S107 (parameters) and
 * S1142 (exit points). As for S3776, the expected values describe the rule, not
 * our output.
 */
final class ReferenceLensConformanceTest extends TestCase
{
    /**
     * @return list<array{0:string,1:int,2:string}>
     */
    public static function parameterCases(): array
    {
        return [
            ['noParams', 0, 'signature vide'],
            ['__construct', 3, 'promoted properties remain parameters'],
            ['variadicCountsOnce', 2, 'le variadique compte pour un'],
            ['defaultsDoNotChangeCount', 3, 'default values remove nothing'],
        ];
    }

    /**
     * @return list<array{0:string,1:int,2:string}>
     */
    public static function returnCases(): array
    {
        return [
            ['noReturn', 0, 'aucune sortie explicite'],
            ['singleReturn', 1, 'sortie unique'],
            ['returnInEachBranch', 3, 'une sortie par branche'],
            ['returnsInClosureBelongToIt', 1, 'les return de la closure sont les siens'],
            ['arrowFunctionReturnExcluded', 1, 'the arrow function\'s implicit return is its own'],
            ['returnsAcrossTryCatchFinally', 2, 'try et catch comptent, finally ne sort pas'],
        ];
    }

    #[DataProvider('parameterCases')]
    public function testParameterCount(string $method, int $expected, string $rationale): void
    {
        self::assertSame($expected, $this->measure(new ParameterCountLens(), $method), $rationale);
    }

    #[DataProvider('returnCases')]
    public function testReturnCount(string $method, int $expected, string $rationale): void
    {
        self::assertSame($expected, $this->measure(new ReturnCountLens(), $method), $rationale);
    }

    private function measure(Lens $lens, string $method): int
    {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/s3776/Signatures.php');
        $ast = (new ParserFactory())->createForNewestSupportedVersion()->parse($source) ?? [];
        /** @var list<Node\Stmt\ClassMethod> $methods */
        $methods = (new NodeFinder())->findInstanceOf($ast, Node\Stmt\ClassMethod::class);

        foreach ($methods as $node) {
            if ((string) $node->name === $method) {
                return (int) $lens->measure($node, $node->stmts ?? []);
            }
        }

        self::fail(sprintf('Method not found in the fixture: %s', $method));
    }
}
