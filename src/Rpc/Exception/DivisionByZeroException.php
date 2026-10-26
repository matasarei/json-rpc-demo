<?php

declare(strict_types=1);

namespace App\Rpc\Exception;

use JsonRPC\Exception\ResponseException;

/**
 * An application error with its own code.
 *
 * The server relays a ResponseException as it is, code and data included, even
 * with internal error masking on: that is how a procedure reports a business
 * error a client is meant to handle.
 */
final class DivisionByZeroException extends ResponseException
{
    public const int CODE = 1001;

    public function __construct(float $dividend)
    {
        parent::__construct('Division by zero', self::CODE, null, ['dividend' => $dividend]);
    }
}
