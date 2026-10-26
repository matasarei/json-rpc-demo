<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rpc\Procedure;

use App\Rpc\Exception\DivisionByZeroException;
use App\Rpc\Procedure\MathProcedures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MathProcedures::class)]
#[CoversClass(DivisionByZeroException::class)]
final class MathProceduresTest extends TestCase
{
    public function testAdds(): void
    {
        $this->assertSame(5, (new MathProcedures())->add(2, 3));
    }

    public function testDivides(): void
    {
        $this->assertSame(2.5, (new MathProcedures())->divide(5, 2));
    }

    public function testReportsADivisionByZeroWithItsOwnCodeAndData(): void
    {
        try {
            (new MathProcedures())->divide(7, 0);
            $this->fail('A division by zero must throw');
        } catch (DivisionByZeroException $exception) {
            $this->assertSame(1001, $exception->getCode());
            $this->assertSame('Division by zero', $exception->getMessage());
            $this->assertSame(['dividend' => 7.0], $exception->getData());
        }
    }
}
