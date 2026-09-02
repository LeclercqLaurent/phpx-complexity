<?php

declare(strict_types=1);

namespace Fixture\Baseline;

final class Sample
{
    public function compute(int $a): int
    {
        if ($a > 0) {
            return $a;
        }

        return 0;
    }
}
