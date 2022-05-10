<?php

namespace App\DependencyInjection\Compiler;

use App\JsonRpc\HandlerCollection;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class JsonRpcPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(HandlerCollection::class)) {
            return;
        }

        $definition = $container->findDefinition(HandlerCollection::class);
        $taggedServices = $container->findTaggedServiceIds('app.json_rpc_handler');

        foreach ($taggedServices as $id => $tags) {
            $definition->addMethodCall('addHandler', [new Reference($id)]);
        }
    }
}
