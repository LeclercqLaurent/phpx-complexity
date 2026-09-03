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
 * Conformité à S3776.
 *
 * Les valeurs attendues viennent de la SPÉCIFICATION (white paper SonarSource
 * « Cognitive Complexity »), pas de la sortie de notre implémentation : c'est la
 * seule façon pour ce test de détecter une réimplémentation fautive. Le README
 * annonce une reproduction native de la règle, cette suite est ce qui rend
 * l'annonce vérifiable.
 */
final class CognitiveComplexityConformanceTest extends TestCase
{
    /**
     * @return list<array{0:string,1:int,2:string}>
     */
    public static function cases(): array
    {
        return [
            ['noBranching', 0, 'aucune structure de contrôle'],
            ['sumOfPrimes', 7, 'exemple canonique : boucle 1 + boucle imbriquée 2 + if 3 + saut 1'],
            ['getWords', 1, 'exemple canonique : un switch coûte 1, quel que soit le nombre de cas'],
            ['withElseif', 3, 'if 1 + elseif 1 + else 1'],
            ['withElseSpaceIf', 3, '« else if » se compte comme « elseif »'],
            ['elseAvoidsNestingPenalty', 4, 'if 1 + if imbriqué 2 + else 1, sans pénalité sur le else'],
            ['nestedConditions', 6, 'boucle 1 + if 2 + boucle 3'],
            ['singleSequence', 2, 'if 1 + une seule séquence &&'],
            ['mixedSequences', 3, 'if 1 + séquence && 1 + séquence || 1'],
            ['negationIsFree', 2, 'if 1 + séquence && 1 ; la négation est gratuite'],
            ['ternary', 1, 'un ternaire coûte 1'],
            ['nestedTernary', 3, 'ternaire 1 + ternaire imbriqué 2'],
            ['catchIncrements', 2, 'deux catch, le try ne coûte rien'],
            ['catchNesting', 3, 'boucle 1 + catch 2 ; continue sans niveau est gratuit'],
            ['closureAddsNestingOnly', 2, 'la closure ne coûte rien mais imbrique le if à 2'],
            ['simpleBreakIsFree', 3, 'boucle 1 + if 2 ; break simple gratuit'],
            ['labelledBreakCounts', 7, 'boucle 1 + boucle 2 + if 3 + break 2 → 1'],
            ['gotoCounts', 2, 'if 1 + goto 1'],
            ['matchLikeSwitch', 1, 'écart assumé : match traité comme un switch'],
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

        // « run » n'est qu'un réceptacle pour les blocs try.
        self::assertSame([], array_values(array_diff($declared, $asserted, ['run'])));
    }

    private function measure(string $method): int
    {
        foreach ($this->methods() as $node) {
            if ((string) $node->name === $method) {
                return (int) (new CognitiveComplexityLens())->measure($node, $node->stmts ?? []);
            }
        }

        self::fail(sprintf('Méthode absente du fixture : %s', $method));
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
