<?php

declare(strict_types=1);

namespace App\Rpc\Attribute;

use Attribute;

/**
 * Publishes a service method as a JSON-RPC procedure.
 *
 * Only methods carrying this attribute are callable: the attribute is the
 * allowlist that json-rpc 2.0 asks for instead of exposing every public method.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class AsRpcProcedure
{
    public function __construct(public string $name)
    {
    }
}
