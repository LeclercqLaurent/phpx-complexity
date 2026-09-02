<?php

declare(strict_types=1);

namespace PhpxComplexity\Lens;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpxComplexity\Ast\AstHelper;

/**
 * Intrication des données : degré moyen du graphe de co-occurrence des variables
 * locales (2·arêtes / sommets). Une arête relie deux variables RÉELLEMENT
 * combinées via un flux de données :
 *   - affectation : la cible dépend de chaque variable du RHS
 *     (`$c = f($a, $b)` → c–a, c–b ; mais PAS a–b : arguments indépendants) ;
 *   - opérateur binaire / ternaire : les deux opérandes sont combinés
 *     (`if ($a && $b)` → a–b).
 *
 * `$this->x = $x` ne produit aucune arête (affectation plate) : c'est ce qui
 * distingue l'entropie ENCHEVÊTRÉE (coûteuse) de l'entropie PLATE d'une factory.
 * Seul axe non couvert par S3776 (branches) ni S107 (signature).
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
        return 'Intrication';
    }

    public function reference(): string
    {
        return '';
    }

    public function description(): string
    {
        return 'Degré moyen du graphe de co-occurrence des variables locales '
            . '(2·arêtes / sommets) : une arête relie deux variables réellement '
            . "combinées par un flux de données. Distingue l'entropie enchevêtrée "
            . "(coûteuse) de l'entropie plate d'une factory. Seul axe hors S3776/S107.";
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

        // Toutes les variables locales comptent comme sommets, même isolées,
        // pour que le degré moyen ne soit pas surévalué.
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
