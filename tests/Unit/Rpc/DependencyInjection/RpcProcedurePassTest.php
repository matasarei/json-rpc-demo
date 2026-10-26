<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rpc\DependencyInjection;

use App\Rpc\DependencyInjection\RpcProcedurePass;
use App\Rpc\ServerFactory;
use App\Tests\Fixture\EchoProcedures;
use App\Tests\Fixture\ReverseProcedures;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(RpcProcedurePass::class)]
final class RpcProcedurePassTest extends TestCase
{
    public function testHandsTheProcedureMapAndALocatorToTheFactory(): void
    {
        $container = $this->container();
        $container->register(EchoProcedures::class, EchoProcedures::class)
            ->addTag(RpcProcedurePass::TAG, ['procedure' => 'text.echo', 'method' => 'echo']);
        $container->register(ReverseProcedures::class, ReverseProcedures::class)
            ->addTag(RpcProcedurePass::TAG, ['procedure' => 'text.reverse', 'method' => 'reverse'])
            ->addTag(RpcProcedurePass::TAG, ['procedure' => 'text.mirror', 'method' => 'reverse']);

        (new RpcProcedurePass())->process($container);

        $factory = $container->getDefinition(ServerFactory::class);
        $this->assertSame(
            [
                'text.echo' => [EchoProcedures::class, 'echo'],
                'text.mirror' => [ReverseProcedures::class, 'reverse'],
                'text.reverse' => [ReverseProcedures::class, 'reverse'],
            ],
            $factory->getArgument('$procedures'),
        );
        $this->assertInstanceOf(Reference::class, $factory->getArgument('$procedureLocator'));
    }

    public function testRefusesAProcedureNameDeclaredTwice(): void
    {
        $container = $this->container();
        $container->register(EchoProcedures::class, EchoProcedures::class)
            ->addTag(RpcProcedurePass::TAG, ['procedure' => 'text.transform', 'method' => 'echo']);
        $container->register(ReverseProcedures::class, ReverseProcedures::class)
            ->addTag(RpcProcedurePass::TAG, ['procedure' => 'text.transform', 'method' => 'reverse']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'JSON-RPC procedure "text.transform" is declared twice: by %s::echo() and by %s::reverse().',
            EchoProcedures::class,
            ReverseProcedures::class,
        ));

        (new RpcProcedurePass())->process($container);
    }

    public function testRefusesTwoServicesOfTheSameClass(): void
    {
        $container = $this->container();
        $container->register('echo.first', EchoProcedures::class)
            ->addTag(RpcProcedurePass::TAG, ['procedure' => 'text.echo', 'method' => 'echo']);
        $container->register('echo.second', EchoProcedures::class)
            ->addTag(RpcProcedurePass::TAG, ['procedure' => 'text.echo_again', 'method' => 'echo']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'Services "echo.first" and "echo.second" both declare JSON-RPC procedures with class %s',
            EchoProcedures::class,
        ));

        (new RpcProcedurePass())->process($container);
    }

    public function testRefusesATagWithoutAProcedureOrAMethod(): void
    {
        $container = $this->container();
        $container->register(EchoProcedures::class, EchoProcedures::class)
            ->addTag(RpcProcedurePass::TAG, ['procedure' => 'text.echo']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'The "app.rpc_procedure" tag of service "%s" needs a "procedure" and a "method" attribute.',
            EchoProcedures::class,
        ));

        (new RpcProcedurePass())->process($container);
    }

    public function testDoesNothingWithoutAServerFactory(): void
    {
        $container = new ContainerBuilder();
        $container->register(EchoProcedures::class, EchoProcedures::class)
            ->addTag(RpcProcedurePass::TAG, ['procedure' => 'text.echo', 'method' => 'echo']);

        (new RpcProcedurePass())->process($container);

        $this->assertFalse($container->hasDefinition(ServerFactory::class));
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(ServerFactory::class, ServerFactory::class);

        return $container;
    }
}
