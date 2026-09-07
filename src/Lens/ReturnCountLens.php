<?php

declare(strict_types=1);

namespace PhpxComplexity\Lens;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpxComplexity\Ast\AstHelper;

/**
 * SonarQube S1142, too many "return" statements. A native reimplementation of the
 * original custom rule. The "return" statements of nested functions are not
 * counted: they belong to their own scope.
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

    public function description(): string
    {
        return 'The number of "return" statements within the scope of the method '
            . '(nested functions count towards their own). Too many exits multiplies '
            . 'the terminal paths and makes the post-condition harder to reason '
            . 'about.';
    }

    public function measure(Node\FunctionLike $function, array $stmts): float
    {
        $visitor = new class () extends NodeVisitorAbstract {
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
