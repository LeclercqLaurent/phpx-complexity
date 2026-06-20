<?php

declare(strict_types=1);

namespace PhpxComplexity\Lens;

use PhpParser\Node;

/**
 * SonarQube S107 — trop de paramètres. Réimplémentation native (php-parser pur,
 * sans couplage PHPStan) de la règle custom Codeam d'origine.
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
        return 'Paramètres';
    }

    public function reference(): string
    {
        return 'S107';
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
