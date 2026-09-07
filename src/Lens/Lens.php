<?php

declare(strict_types=1);

namespace PhpxComplexity\Lens;

use PhpParser\Node;

/**
 * A "lens" measures one facet of the complexity of a method or function. Every
 * lens produces a raw value comparable to a threshold; the divergence report
 * plays the ranks of all lenses against each other to spot the methods where
 * they contradict one another, which is the blind spot of isolated metrics.
 */
interface Lens
{
    /** A short, stable key ("cognitive", "params"). */
    public function key(): string;

    /** A readable label. */
    public function label(): string;

    /** The SonarQube reference when applicable ("S3776"), otherwise an empty string. */
    public function reference(): string;

    /** A readable definition of what the lens measures, in one or two sentences. */
    public function description(): string;

    /**
     * The raw value for a function or method.
     *
     * @param Node\Stmt[] $stmts the body of the function (never null)
     */
    public function measure(Node\FunctionLike $function, array $stmts): float;

    /** The default threshold beyond which the value counts as high. */
    public function defaultThreshold(): float;
}
