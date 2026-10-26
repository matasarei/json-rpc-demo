<?php

declare(strict_types=1);

namespace App\Rpc\DependencyInjection;

use App\Rpc\ServerFactory;
use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collects the methods marked with #[AsRpcProcedure] for the ServerFactory.
 *
 * The factory receives the procedure map and a service locator, so a procedure
 * class is only built when one of its procedures is called.
 */
final class RpcProcedurePass implements CompilerPassInterface
{
    public const string TAG = 'app.rpc_procedure';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ServerFactory::class)) {
            return;
        }

        $procedures = [];
        $services = [];

        foreach ($container->findTaggedServiceIds(self::TAG) as $id => $tags) {
            $class = $container->getDefinition($id)->getClass() ?? $id;

            foreach ($tags as $tag) {
                [$procedure, $method] = $this->procedureOf($id, $tag);

                if (isset($procedures[$procedure])) {
                    throw new LogicException(sprintf(
                        'JSON-RPC procedure "%s" is declared twice: by %s::%s() and by %s::%s().',
                        $procedure,
                        $procedures[$procedure][0],
                        $procedures[$procedure][1],
                        $class,
                        $method,
                    ));
                }

                $procedures[$procedure] = [$class, $method];
            }

            $services[$class] = new Reference($id);
        }

        ksort($procedures);

        $container->getDefinition(ServerFactory::class)
            ->setArgument('$procedures', $procedures)
            ->setArgument('$procedureLocator', ServiceLocatorTagPass::register($container, $services));
    }

    /**
     * @return array{string, string} Procedure name and method
     */
    private function procedureOf(string $id, mixed $tag): array
    {
        $procedure = is_array($tag) ? $tag['procedure'] ?? null : null;
        $method = is_array($tag) ? $tag['method'] ?? null : null;

        if (!is_string($procedure) || !is_string($method)) {
            throw new LogicException(sprintf(
                'The "%s" tag of service "%s" needs a "procedure" and a "method" attribute.',
                self::TAG,
                $id,
            ));
        }

        return [$procedure, $method];
    }
}
