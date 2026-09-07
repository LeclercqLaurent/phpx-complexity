<?php

declare(strict_types=1);

namespace PhpxComplexity\Lens;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpxComplexity\Ast\AstHelper;

/**
 * SonarQube S3776, cognitive complexity (the core of the SonarSource white
 * paper), reimplemented natively to avoid any dependency on PHPStan or its
 * extensions.
 *
 * Three families of increments:
 *  1. STRUCTURAL: +1 for if / elseif / else, loops, switch, catch, ternary,
 *     match, and each sequence of logical operators (&&, ||).
 *  2. NESTING: an extra +N for nested structures (if, loops, switch, catch,
 *     ternary, match), where N is the current nesting level.
 *  3. LABELLED JUMP: +1 for `goto`, and for `break N` / `continue N` with
 *     N > 1. PHP has no loop labels, so the equivalent of the spec's
 *     `break LABEL` is leaving several structures at once. A plain `break;`
 *     costs nothing.
 *  4. Nested functions (closures, arrow functions) raise the nesting level
 *     without a structural increment of their own.
 *
 * Deliberate deviations from the spec:
 *  - **Recursion**: the spec adds +1 per method of a recursive cycle; not
 *    implemented, as it would require interprocedural analysis.
 *  - **Two-word `else if`**: php-parser produces the same AST as
 *    `else { if ... }`, with no way to tell them apart without the source text.
 *    Both are treated as `elseif` (+1, with no nesting penalty), which follows
 *    PHP's own semantics, where `else if` and `elseif` are identical. The rare
 *    `else { if ... }` form is therefore slightly under-counted, which is
 *    preferred to over-counting the common form by +2.
 *  - **`match`**: postdates the spec, treated as a `switch`.
 */
final class CognitiveComplexityLens implements Lens
{
    public function __construct(private readonly float $threshold = 15.0)
    {
    }

    public function key(): string
    {
        return 'cognitive';
    }

    public function label(): string
    {
        return 'Cognitive';
    }

    public function reference(): string
    {
        return 'S3776';
    }

    public function description(): string
    {
        return 'The mental effort of following the control flow: +1 per branch '
            . '(if/else, loop, switch, catch, ternary, &&/|| sequence) plus an extra '
            . '+N for the nesting level. It measures reading difficulty, not the '
            . 'number of paths, so it is not cyclomatic complexity.';
    }

    public function measure(Node\FunctionLike $function, array $stmts): float
    {
        return (float) $this->walkList($stmts, 0, null);
    }

    public function defaultThreshold(): float
    {
        return $this->threshold;
    }

    /**
     * @param Node[] $nodes
     */
    private function walkList(array $nodes, int $nesting, ?string $parentLogical): int
    {
        $sum = 0;
        foreach ($nodes as $node) {
            $sum += $this->walk($node, $nesting, $parentLogical);
        }

        return $sum;
    }

    private function walk(Node $node, int $nesting, ?string $parentLogical): int
    {
        return match (true) {
            $node instanceof Stmt\If_ => $this->walkIf($node, $nesting),
            $node instanceof Stmt\For_,
            $node instanceof Stmt\Foreach_,
            $node instanceof Stmt\While_,
            $node instanceof Stmt\Do_ => $this->walkLoop($node, $nesting),
            $node instanceof Stmt\Switch_ => $this->walkSwitch($node, $nesting),
            $node instanceof Stmt\TryCatch => $this->walkTry($node, $nesting),
            $node instanceof Stmt\Goto_ => 1,
            $node instanceof Stmt\Break_,
            $node instanceof Stmt\Continue_ => $this->walkJump($node),
            $node instanceof Expr\Ternary,
            $node instanceof Expr\Match_ => $this->walkNesting($node, $nesting),
            $node instanceof Node\FunctionLike => $this->walkList(AstHelper::childNodes($node), $nesting + 1, null),
            $node instanceof Expr\BinaryOp\BooleanAnd,
            $node instanceof Expr\BinaryOp\BooleanOr,
            $node instanceof Expr\BinaryOp\LogicalAnd,
            $node instanceof Expr\BinaryOp\LogicalOr => $this->walkLogical($node, $nesting, $parentLogical),
            default => $this->walkList(AstHelper::childNodes($node), $nesting, null),
        };
    }

    private function walkIf(Stmt\If_ $node, int $nesting): int
    {
        $sum = 1 + $nesting;
        $sum += $this->walk($node->cond, $nesting, null);
        $sum += $this->walkList($node->stmts, $nesting + 1, null);

        return $sum + $this->walkElseBranches($node, $nesting);
    }

    /**
     * Each "else" branch costs +1 WITHOUT a nesting penalty: the spec does not
     * charge for an `else` twice, since the reader stays at the same level. A
     * two-word `else if` is flattened into the chain rather than treated as an
     * `else` containing a nested `if`.
     */
    private function walkElseBranches(Stmt\If_ $node, int $nesting): int
    {
        $sum = 0;
        foreach ($node->elseifs as $elseif) {
            $sum += 1 + $this->walk($elseif->cond, $nesting, null);
            $sum += $this->walkList($elseif->stmts, $nesting + 1, null);
        }

        if (null === $node->else) {
            return $sum;
        }

        $chained = $this->chainedIf($node->else);
        if (null === $chained) {
            return $sum + 1 + $this->walkList($node->else->stmts, $nesting + 1, null);
        }

        $sum += 1 + $this->walk($chained->cond, $nesting, null);
        $sum += $this->walkList($chained->stmts, $nesting + 1, null);

        return $sum + $this->walkElseBranches($chained, $nesting);
    }

    /**
     * The single `if` inside an `else`, which therefore forms an "else if".
     */
    private function chainedIf(Stmt\Else_ $else): ?Stmt\If_
    {
        return 1 === count($else->stmts) && $else->stmts[0] instanceof Stmt\If_
            ? $else->stmts[0]
            : null;
    }

    /**
     * A jump out of several structures: PHP's equivalent of the spec's
     * `break LABEL`. A plain `break;` or `continue;` costs nothing.
     */
    private function walkJump(Stmt\Break_|Stmt\Continue_ $node): int
    {
        return $node->num instanceof Node\Scalar\Int_ && $node->num->value > 1 ? 1 : 0;
    }

    private function walkLoop(Stmt\For_|Stmt\Foreach_|Stmt\While_|Stmt\Do_ $node, int $nesting): int
    {
        $sum = 1 + $nesting;
        foreach (AstHelper::childNodes($node) as $child) {
            $inBody = in_array($child, $node->stmts, true);
            $sum += $this->walk($child, $inBody ? $nesting + 1 : $nesting, null);
        }

        return $sum;
    }

    private function walkSwitch(Stmt\Switch_ $node, int $nesting): int
    {
        $sum = 1 + $nesting;
        $sum += $this->walk($node->cond, $nesting, null);
        foreach ($node->cases as $case) {
            if (null !== $case->cond) {
                $sum += $this->walk($case->cond, $nesting + 1, null);
            }
            $sum += $this->walkList($case->stmts, $nesting + 1, null);
        }

        return $sum;
    }

    private function walkTry(Stmt\TryCatch $node, int $nesting): int
    {
        $sum = $this->walkList($node->stmts, $nesting, null);
        foreach ($node->catches as $catch) {
            $sum += 1 + $nesting + $this->walkList($catch->stmts, $nesting + 1, null);
        }
        if (null !== $node->finally) {
            $sum += $this->walkList($node->finally->stmts, $nesting, null);
        }

        return $sum;
    }

    private function walkNesting(Expr\Ternary|Expr\Match_ $node, int $nesting): int
    {
        return 1 + $nesting + $this->walkList(AstHelper::childNodes($node), $nesting + 1, null);
    }

    private function walkLogical(Node $node, int $nesting, ?string $parentLogical): int
    {
        $class = $node::class;
        $increment = $class === $parentLogical ? 0 : 1;

        return $increment + $this->walkList(AstHelper::childNodes($node), $nesting, $class);
    }
}
