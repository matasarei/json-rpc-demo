<?php

declare(strict_types=1);

namespace App\Tests\Fixture;

final class EchoProcedures
{
    public function echo(string $value): string
    {
        return $value;
    }
}
