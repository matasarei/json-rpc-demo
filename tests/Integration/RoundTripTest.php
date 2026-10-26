<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Command\RpcDemoCommand;
use App\Rpc\RpcClientFactory;
use JsonRPC\Client;
use JsonRPC\Exception\AccessDeniedException;
use JsonRPC\Exception\BatchFailedException;
use JsonRPC\Exception\MethodNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The client and the server of the demo talking to each other: the real
 * JsonRPC\Client sends over PSR-18 to a MockHttpClient that hands the request
 * to the kernel, so everything but the socket is the code that runs in production.
 */
#[CoversClass(RpcClientFactory::class)]
#[CoversClass(RpcDemoCommand::class)]
final class RoundTripTest extends KernelTestCase
{
    public function testCallsAProcedure(): void
    {
        $this->assertSame('Hello, Ada!', $this->client()->execute('greeting.hello', ['name' => 'Ada']));
    }

    public function testCorrelatesABatchAndReportsItsFailingCall(): void
    {
        try {
            $this->client()->batch()
                ->execute('math.add', [1, 2])
                ->execute('math.pow', [2, 8])
                ->execute('greeting.hello', ['name' => 'Grace'])
                ->send();
            $this->fail('A batch with a failing call must throw');
        } catch (BatchFailedException $failure) {
            $this->assertSame([0 => 3, 2 => 'Hello, Grace!'], $failure->getResults());
            $this->assertInstanceOf(MethodNotFoundException::class, $failure->getErrors()[1] ?? null);
        }
    }

    public function testReportsWrongCredentials(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('401');

        $this->client('demo', 'wrong')->execute('greeting.hello');
    }

    public function testReportsAProcedureTheUserMayNotCall(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('403');

        $this->client()->execute('admin.versions');
    }

    public function testRunsTheWholeTourSuccessfully(): void
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find('app:rpc:demo'));

        $tester->execute([]);

        $tester->assertCommandIsSuccessful($tester->getDisplay());
        $this->assertSame(11, substr_count($tester->getDisplay(), '✔'));
        $this->assertStringContainsString('Every scenario behaved as expected.', $tester->getDisplay());
    }

    private function client(string $username = 'demo', string $password = 'demo-secret'): Client
    {
        $factory = self::getContainer()->get(RpcClientFactory::class);
        $this->assertInstanceOf(RpcClientFactory::class, $factory);

        return $factory->create($username, $password);
    }
}
