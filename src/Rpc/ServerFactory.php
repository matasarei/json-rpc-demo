<?php

declare(strict_types=1);

namespace App\Rpc;

use JsonRPC\MiddlewareInterface;
use JsonRPC\Server;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Builds the JSON-RPC server the controller answers with.
 */
final readonly class ServerFactory
{
    public const string MIDDLEWARE_TAG = 'app.rpc_middleware';

    /**
     * @param array<string, array{class-string, string}> $procedures Class and method keyed by
     *        procedure name, filled in by RpcProcedurePass
     * @param ServiceProviderInterface<object> $procedureLocator Procedure services keyed by class name
     * @param array<string, string> $users Passwords keyed by username
     * @param iterable<MiddlewareInterface> $middlewares Every service implementing MiddlewareInterface
     */
    public function __construct(
        private array $procedures,
        private ServiceProviderInterface $procedureLocator,
        #[Autowire(env: 'json:RPC_USERS')]
        private array $users,
        #[Autowire(env: 'int:RPC_BATCH_LIMIT')]
        private int $batchLimit,
        #[AutowireIterator(self::MIDDLEWARE_TAG)]
        private iterable $middlewares = [],
    ) {
    }

    public function create(): Server
    {
        $server = (new Server())
            ->authentication($this->users)
            ->withBatchLimit($this->batchLimit);

        foreach ($this->middlewares as $middleware) {
            $server->getMiddlewareHandler()->withMiddleware($middleware);
        }

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
