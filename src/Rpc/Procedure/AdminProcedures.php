<?php

declare(strict_types=1);

namespace App\Rpc\Procedure;

use App\Rpc\Attribute\AsRpcProcedure;
use App\Rpc\Middleware\AdminOnlyMiddleware;
use Composer\InstalledVersions;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Reserved for the admin user by AdminOnlyMiddleware.
 */
final readonly class AdminProcedures
{
    /**
     * @return array{php: string, symfony: string, json_rpc: string|null}
     */
    #[AsRpcProcedure(AdminOnlyMiddleware::PREFIX . 'versions')]
    public function versions(): array
    {
        return [
            'php' => PHP_VERSION,
            'symfony' => Kernel::VERSION,
            'json_rpc' => InstalledVersions::getPrettyVersion('fguillot/json-rpc'),
        ];
    }
}
