<?php

declare(strict_types=1);

namespace App;

use App\Rpc\Attribute\AsRpcProcedure;
use App\Rpc\DependencyInjection\RpcProcedurePass;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function build(ContainerBuilder $container): void
    {
        // Symfony reads the type of the third parameter to know the attribute
        // sits on methods; the declared signature only promises a Reflector.
        $container->registerAttributeForAutoconfiguration(
            AsRpcProcedure::class,
            // @phpstan-ignore argument.type
            static function (ChildDefinition $definition, AsRpcProcedure $attribute, ReflectionMethod $method): void {
                $definition->addTag(RpcProcedurePass::TAG, [
                    'procedure' => $attribute->name,
                    'method' => $method->getName(),
                ]);
            },
        );

        $container->addCompilerPass(new RpcProcedurePass());
    }
}
