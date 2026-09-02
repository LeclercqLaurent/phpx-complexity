<?php

declare(strict_types=1);

namespace Fixture\VarRoot;

final class Foo
{
    public function compute(int $a, int $b): int
    {
        return $a + $b;
    }
}
