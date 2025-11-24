<?php

declare(strict_types=1);

namespace Peso\Services\Tests;

use PHPUnit\Framework\TestCase;

final class DummyTest extends TestCase
{
    public function testNothing(): void
    {
        self::assertEquals(2, 1 + 1);
    }
}
