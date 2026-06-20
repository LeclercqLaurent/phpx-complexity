<?php

declare(strict_types=1);

namespace Sample\Tests;

use PHPUnit\Framework\TestCase;

class FooTest extends TestCase
{
    public function testA(): void
    {
        self::assertSame(1, 1);
    }

    public function testB(): void
    {
        self::assertSame(2, 2);
    }
}
