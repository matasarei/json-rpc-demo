<?php

declare(strict_types=1);

namespace App\Tests\Fixture;

final class ReverseProcedures
{
    public function reverse(string $value): string
    {
        return strrev($value);
    }
}
