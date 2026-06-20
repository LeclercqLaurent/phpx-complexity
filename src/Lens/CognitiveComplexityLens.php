<?php

declare(strict_types=1);

namespace PhpxComplexity\Lens;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpxComplexity\Ast\AstHelper;

/**
 * SonarQube S3776 — complexité cognitive (cœur du white paper SonarSource),
 * réimplémentée nativement pour éviter toute dépendance à PHPStan / extensions.
 *
 * Trois familles d'incréments :
 *  1. STRUCTUREL : +1 pour if / elseif / else, boucles, switch, catch, ternaire,
 *     match, et chaque séquence d'opérateurs logiques (&&, ||).
 *  2. IMBRICATION : +N supplémentaire pour les structures imbriquées (if, boucles,
 *     switch, catch, ternaire, match), où N = niveau d'imbrication courant.
 *  3. Les fonctions imbriquées (closures, arrow fn) augmentent le niveau
 *     d'imbrication sans incrément structurel propre.
 *
 * Non couvert (rare, volontairement omis) : +1 sur récursion directe.
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
        return 'Effort mental pour suivre le flux de contrôle : +1 par branche '
            . '(if/else, boucle, switch, catch, ternaire, séquence &&/||) et +N '
            . "supplémentaire selon le niveau d'imbrication. Mesure la difficulté de "
            . 'lecture, pas le nombre de chemins (≠ complexité cyclomatique).';
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

        foreach ($node->elseifs as $elseif) {
            $sum += 1 + $this->walk($elseif->cond, $nesting, null);
            $sum += $this->walkList($elseif->stmts, $nesting + 1, null);
        }

        if (null !== $node->else) {
            $sum += 1 + $this->walkList($node->else->stmts, $nesting + 1, null);
        }

        return $sum;
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
