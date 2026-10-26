<?php

declare(strict_types=1);

namespace App\Rpc\Procedure;

use App\Rpc\Attribute\AsRpcProcedure;
use Psr\Log\LoggerInterface;
use RuntimeException;

final readonly class DiagnosticsProcedures
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    /**
     * Meant to be sent as a notification: the client expects no answer.
     */
    #[AsRpcProcedure('diagnostics.log')]
    public function log(string $message): bool
    {
        $this->logger->info('Client says: {message}', ['message' => $message]);

        return true;
    }

    /**
     * Fails the way a broken dependency does. With internal error masking on,
     * the default in json-rpc 2.0, the client only sees -32603 Internal error.
     */
    #[AsRpcProcedure('diagnostics.fail')]
    public function fail(): never
    {
        throw new RuntimeException('Cannot connect to mysql://app:s3cr3t-db-password@10.0.0.5/app');
    }
}
