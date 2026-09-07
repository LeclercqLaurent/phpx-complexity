<?php

declare(strict_types=1);

namespace PhpxComplexity\Lens;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpxComplexity\Ast\AstHelper;

/**
 * Data entanglement: the average degree of the co-occurrence graph of local
 * variables (2*edges / vertices). An edge links two variables REALLY combined
 * through a data flow:
 *   - assignment: the target depends on every variable of the right-hand side
 *     (`$c = f($a, $b)` gives c-a and c-b, but NOT a-b: independent arguments);
 *   - binary or ternary operator: both operands are combined
 *     (`if ($a && $b)` gives a-b).
 *
 * `$this->x = $x` produces no edge at all (a flat assignment), and that is what
 * separates INTERWOVEN entropy (expensive) from the FLAT entropy of a factory.
 * It is the only axis covered by neither S3776 (branching) nor S107 (signature).
 */
final class EntanglementLens implements Lens
{
    public function __construct(private readonly float $threshold = 3.0)
    {
    }

    public function key(): string
    {
        return 'entangle';
    }

    public function label(): string
    {
        return 'Entanglement';
    }

    public function reference(): string
    {
        return '';
    }

    public function description(): string
    {
        return 'The average degree of the co-occurrence graph of local variables '
            . '(2*edges / vertices): an edge links two variables really combined by '
            . 'a data flow. It separates interwoven entropy (expensive) from the '
            . 'flat entropy of a factory. The only axis outside S3776 and S107.';
    }

    public function measure(Node\FunctionLike $function, array $stmts): float
    {
        $visitor = new class () extends NodeVisitorAbstract {
            /** @var array<string,true> */
            public array $nodes = [];
            /** @var array<string,true> */
            public array $edges = [];
            private bool $isRoot = true;

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Node\FunctionLike && !$this->isRoot) {
                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }
                $this->isRoot = false;

                if ($node instanceof Node\Expr\Assign
                    || $node instanceof Node\Expr\AssignOp
                    || $node instanceof Node\Expr\AssignRef) {
                    $this->link(AstHelper::localVariables($node->var), AstHelper::localVariables($node->expr));
                } elseif ($node instanceof Node\Expr\BinaryOp) {
                    $this->link(AstHelper::localVariables($node->left), AstHelper::localVariables($node->right));
                } elseif ($node instanceof Node\Expr\Ternary) {
                    $branches = array_merge(
                        AstHelper::localVariables($node->if ?? $node->cond),
                        AstHelper::localVariables($node->else),
                    );
                    $this->link(AstHelper::localVariables($node->cond), $branches);
                }

                return null;
            }

            /**
             * @param list<string> $left
             * @param list<string> $right
             */
            private function link(array $left, array $right): void
            {
                foreach (array_merge($left, $right) as $name) {
                    $this->nodes[$name] = true;
                }
                foreach ($left as $a) {
                    foreach ($right as $b) {
                        if ($a === $b) {
                            continue;
                        }
                        $this->edges[$a < $b ? "$a|$b" : "$b|$a"] = true;
                    }
                }
            }
        };

        AstHelper::traverse($stmts, $visitor);

        // Every local variable counts as a vertex, isolated ones included, so
        // that the average degree is not overstated.
        foreach (AstHelper::variableSpans($stmts) as $name => $_) {
            $visitor->nodes[$name] = true;
        }

        $vertexCount = count($visitor->nodes);
        if ($vertexCount < 2) {
            return 0.0;
        }

        return (2.0 * count($visitor->edges)) / $vertexCount;
    }

    public function defaultThreshold(): float
    {
        return $this->threshold;
    }
}
