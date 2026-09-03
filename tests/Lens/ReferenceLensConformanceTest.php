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
 * Conformité des deux autres règles SonarQube reproduites : S107 (paramètres) et
 * S1142 (points de sortie). Comme pour S3776, les valeurs attendues décrivent la
 * règle, pas notre sortie.
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
            ['__construct', 3, 'les propriétés promues restent des paramètres'],
            ['variadicCountsOnce', 2, 'le variadique compte pour un'],
            ['defaultsDoNotChangeCount', 3, 'les valeurs par défaut ne retirent rien'],
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
            ['arrowFunctionReturnExcluded', 1, 'le return implicite de la fonction fléchée est le sien'],
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

        self::fail(sprintf('Méthode absente du fixture : %s', $method));
    }
}
