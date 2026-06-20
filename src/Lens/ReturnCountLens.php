<?php

declare(strict_types=1);

namespace PhpxComplexity\Lens;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpxComplexity\Ast\AstHelper;

/**
 * SonarQube S1142 — trop d'instructions « return ». Réimplémentation native de la
 * règle custom Codeam d'origine. Les « return » des fonctions imbriquées ne sont
 * pas comptés : ils relèvent de leur propre portée.
 */
final class ReturnCountLens implements Lens
{
    public function __construct(private readonly float $threshold = 3.0)
    {
    }

    public function key(): string
    {
        return 'returns';
    }

    public function label(): string
    {
        return 'Returns';
    }

    public function reference(): string
    {
        return 'S1142';
    }

    public function measure(Node\FunctionLike $function, array $stmts): float
    {
        $visitor = new class extends NodeVisitorAbstract {
            public int $count = 0;

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Node\FunctionLike) {
                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }
                if ($node instanceof Node\Stmt\Return_) {
                    ++$this->count;
                }

                return null;
            }
        };

        AstHelper::traverse($stmts, $visitor);

        return (float) $visitor->count;
    }

    public function defaultThreshold(): float
    {
        return $this->threshold;
    }
}
