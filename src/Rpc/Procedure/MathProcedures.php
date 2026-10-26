<?php

declare(strict_types=1);

namespace App\Rpc\Procedure;

use App\Rpc\Attribute\AsRpcProcedure;
use App\Rpc\Exception\DivisionByZeroException;

final readonly class MathProcedures
{
    /**
     * Types are not coerced: ["5", "3"] is answered with -32602 Invalid params.
     */
    #[AsRpcProcedure('math.add')]
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    /**
     * @throws DivisionByZeroException Relayed to the client as error 1001
     */
    #[AsRpcProcedure('math.divide')]
    public function divide(float $dividend, float $divisor): float
    {
        if ($divisor === 0.0) {
            throw new DivisionByZeroException($dividend);
        }

        return $dividend / $divisor;
    }
}
