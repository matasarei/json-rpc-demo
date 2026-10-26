<?php

declare(strict_types=1);

namespace App\Rpc\Procedure;

use App\Rpc\Attribute\AsRpcProcedure;

final readonly class GreetingProcedures
{
    /**
     * Callable with named ({"name": "Ada"}), positional (["Ada"]) or no parameters.
     */
    #[AsRpcProcedure('greeting.hello')]
    public function hello(string $name = 'World'): string
    {
        return sprintf('Hello, %s!', $name);
    }
}
