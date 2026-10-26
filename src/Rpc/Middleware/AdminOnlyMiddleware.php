<?php

declare(strict_types=1);

namespace App\Rpc\Middleware;

use JsonRPC\Exception\AccessDeniedException;
use JsonRPC\MiddlewareInterface;

/**
 * Keeps the admin.* procedures for the admin user; the server answers 403 to anyone else.
 */
final readonly class AdminOnlyMiddleware implements MiddlewareInterface
{
    public const string PREFIX = 'admin.';
    public const string USERNAME = 'admin';

    public function execute(?string $username, ?string $password, string $procedureName): void
    {
        if (str_starts_with($procedureName, self::PREFIX) && $username !== self::USERNAME) {
            throw new AccessDeniedException(sprintf('Procedure "%s" is reserved for the admin user', $procedureName));
        }
    }
}
