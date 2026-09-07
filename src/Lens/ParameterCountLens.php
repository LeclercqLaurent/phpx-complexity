<?php

declare(strict_types=1);

namespace PhpxComplexity\Lens;

use PhpParser\Node;

/**
 * SonarQube S107, too many parameters. A native reimplementation (pure
 * php-parser, no PHPStan coupling) of the original custom rule.
 */
final class ParameterCountLens implements Lens
{
    public function __construct(private readonly float $threshold = 7.0)
    {
    }

    public function key(): string
    {
        return 'params';
    }

    public function label(): string
    {
        return 'Parameters';
    }

    public function reference(): string
    {
        return 'S107';
    }

    public function description(): string
    {
        return 'The number of parameters in the signature. It measures the degrees '
            . 'of freedom on input: past the threshold, the call becomes hard to '
            . 'memorise and often betrays a responsibility to split or an object to '
            . 'introduce.';
    }

    public function measure(Node\FunctionLike $function, array $stmts): float
    {
        return (float) count($function->getParams());
    }

    public function defaultThreshold(): float
    {
        return $this->threshold;
    }
}
