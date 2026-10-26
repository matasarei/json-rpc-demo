<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rpc\Procedure;

use App\Rpc\Procedure\GreetingProcedures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GreetingProcedures::class)]
final class GreetingProceduresTest extends TestCase
{
    public function testGreetsByName(): void
    {
        $this->assertSame('Hello, Ada!', (new GreetingProcedures())->hello('Ada'));
    }

    public function testGreetsTheWorldByDefault(): void
    {
        $this->assertSame('Hello, World!', (new GreetingProcedures())->hello());
    }
}
