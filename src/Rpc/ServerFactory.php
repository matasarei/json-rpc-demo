<?php

declare(strict_types=1);

namespace App\Rpc;

use JsonRPC\Server;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds the JSON-RPC server the controller answers with.
 */
final readonly class ServerFactory
{
    /**
     * @param array<string, array{class-string, string}> $procedures Class and method keyed by
     *        procedure name, filled in by RpcProcedurePass
     * @param ContainerInterface $procedureLocator Procedure services keyed by class name
     * @param array<string, string> $users Passwords keyed by username
     */
    public function __construct(
        private array $procedures,
        private ContainerInterface $procedureLocator,
        #[Autowire(env: 'json:RPC_USERS')]
        private array $users,
        #[Autowire(env: 'int:RPC_BATCH_LIMIT')]
        private int $batchLimit,
    ) {
    }

    public function create(): Server
    {
        $server = (new Server())
            ->authentication($this->users)
            ->withBatchLimit($this->batchLimit);

        // Procedures are registered by class name: the container builds the
        // instance on the first call instead of the factory building them all.
        $handler = $server->getProcedureHandler()->withInstanceFactory(
            fn (string $class): object => $this->procedureLocator->get($class),
        );

        foreach ($this->procedures as $procedure => [$class, $method]) {
            $handler->withClassAndMethod($procedure, $class, $method);
        }

        return $server;
    }
}
