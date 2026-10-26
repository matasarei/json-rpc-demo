<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rpc;

use App\Rpc\ServerFactory;
use App\Tests\Fixture\EchoProcedures;
use App\Tests\Fixture\ReverseProcedures;
use JsonRPC\Server\ServerRequest;
use JsonRPC\Server\ServerResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(ServerFactory::class)]
final class ServerFactoryTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $built = [];

    public function testBuildsAProcedureClassOnlyWhenOneOfItsProceduresIsCalled(): void
    {
        $response = $this->call($this->factory(), ['jsonrpc' => '2.0', 'method' => 'text.echo', 'params' => ['hi'], 'id' => 1]);

        $this->assertSame(['jsonrpc' => '2.0', 'result' => 'hi', 'id' => 1], $this->decode($response));
        $this->assertSame([EchoProcedures::class], $this->built);
    }

    public function testRefusesAnUnknownUser(): void
    {
        $request = ServerRequest::fromString('{"jsonrpc":"2.0","method":"text.echo","params":["hi"],"id":1}', [
            'PHP_AUTH_USER' => 'demo',
            'PHP_AUTH_PW' => 'wrong',
        ]);

        $this->assertSame(401, $this->factory()->create()->execute($request)->statusCode);
        $this->assertSame([], $this->built);
    }

    public function testRejectsABatchOverTheLimit(): void
    {
        $call = ['jsonrpc' => '2.0', 'method' => 'text.echo', 'params' => ['hi'], 'id' => 1];

        $response = $this->call($this->factory(batchLimit: 2), [$call, $call, $call]);

        $this->assertSame(-32600, $this->decode($response)['error']['code'] ?? null);
        $this->assertSame([], $this->built);
    }

    private function factory(int $batchLimit = 20): ServerFactory
    {
        $locator = new ServiceLocator([
            EchoProcedures::class => function (): EchoProcedures {
                $this->built[] = EchoProcedures::class;

                return new EchoProcedures();
            },
            ReverseProcedures::class => function (): ReverseProcedures {
                $this->built[] = ReverseProcedures::class;

                return new ReverseProcedures();
            },
        ]);

        return new ServerFactory(
            [
                'text.echo' => [EchoProcedures::class, 'echo'],
                'text.reverse' => [ReverseProcedures::class, 'reverse'],
            ],
            $locator,
            ['demo' => 'demo-secret'],
            $batchLimit,
        );
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private function call(ServerFactory $factory, array $payload): ServerResponse
    {
        return $factory->create()->execute(ServerRequest::fromString(json_encode($payload, JSON_THROW_ON_ERROR), [
            'PHP_AUTH_USER' => 'demo',
            'PHP_AUTH_PW' => 'demo-secret',
        ]));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(ServerResponse $response): array
    {
        $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
