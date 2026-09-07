<?php

declare(strict_types=1);

namespace PhpxComplexity\Ast;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Analysis helpers shared by the lenses. Every traversal ignores nested
 * functions (closures, arrow functions), whose variables and "return" statements
 * belong to their own scope, with the exception of the root node itself.
 */
final class AstHelper
{
    /**
     * Variables locales (hors $this) d'un sous-arbre, sans entrer dans les
     * nested functions.
     *
     * @return list<string>
     */
    public static function localVariables(Node $root): array
    {
        $visitor = new class () extends NodeVisitorAbstract {
            /** @var array<string,true> */
            public array $names = [];
            private bool $isRoot = true;

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Node\FunctionLike && !$this->isRoot) {
                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }
                $this->isRoot = false;
                if ($node instanceof Node\Expr\Variable
                    && is_string($node->name)
                    && 'this' !== $node->name) {
                    $this->names[$node->name] = true;
                }

                return null;
            }
        };

        self::traverse([$root], $visitor);

        return array_keys($visitor->names);
    }

    /**
     * The lifetime [first occurrence, last occurrence] of each local variable of
     * a method body.
     *
     * @param Node\Stmt[] $stmts
     *
     * @return array<string,array{0:int,1:int}>
     */
    public static function variableSpans(array $stmts): array
    {
        $visitor = new class () extends NodeVisitorAbstract {
            /** @var array<string,array{0:int,1:int}> */
            public array $spans = [];

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Node\FunctionLike) {
                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }
                if ($node instanceof Node\Expr\Variable
                    && is_string($node->name)
                    && 'this' !== $node->name) {
                    $line = $node->getStartLine();
                    if (!isset($this->spans[$node->name])) {
                        $this->spans[$node->name] = [$line, $line];
                    } else {
                        $this->spans[$node->name][0] = min($this->spans[$node->name][0], $line);
                        $this->spans[$node->name][1] = max($this->spans[$node->name][1], $line);
                    }
                }

                return null;
            }
        };

        self::traverse($stmts, $visitor);

        return $visitor->spans;
    }

    /**
     * The direct children of a node, flattened (a subnode may be a Node, an
     * array of Nodes, or an ignored scalar).
     *
     * @return list<Node>
     */
    public static function childNodes(Node $node): array
    {
        $children = [];
        foreach ($node->getSubNodeNames() as $name) {
            $value = $node->{$name};
            if ($value instanceof Node) {
                $children[] = $value;
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    if ($item instanceof Node) {
                        $children[] = $item;
                    }
                }
            }
        }

        return $children;
    }

    /**
     * @param Node[] $nodes
     */
    public static function traverse(array $nodes, NodeVisitorAbstract $visitor): void
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($nodes);
    }
}
