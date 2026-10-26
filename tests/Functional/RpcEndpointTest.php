<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Controller\RpcController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * The /rpc endpoint, driven over HTTP through the Symfony kernel.
 */
#[CoversClass(RpcController::class)]
final class RpcEndpointTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testAnswersACallWithNamedParameters(): void
    {
        $this->post(['jsonrpc' => '2.0', 'method' => 'greeting.hello', 'params' => ['name' => 'Ada'], 'id' => 1]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertSame(['jsonrpc' => '2.0', 'result' => 'Hello, Ada!', 'id' => 1], $this->answer());
    }

    public function testAnswersACallWithPositionalParameters(): void
    {
        $this->post(['jsonrpc' => '2.0', 'method' => 'math.add', 'params' => [2, 3], 'id' => 'sum']);

        $this->assertSame(['jsonrpc' => '2.0', 'result' => 5, 'id' => 'sum'], $this->answer());
    }

    public function testAnswersEveryCallOfABatchExceptItsNotifications(): void
    {
        $this->post([
            ['jsonrpc' => '2.0', 'method' => 'greeting.hello', 'id' => 1],
            ['jsonrpc' => '2.0', 'method' => 'diagnostics.log', 'params' => ['batched']],
            ['jsonrpc' => '2.0', 'method' => 'math.divide', 'params' => [1, 0], 'id' => 2],
        ]);

        $this->assertSame([
            ['jsonrpc' => '2.0', 'result' => 'Hello, World!', 'id' => 1],
            [
                'jsonrpc' => '2.0',
                'error' => ['code' => 1001, 'message' => 'Division by zero', 'data' => ['dividend' => 1]],
                'id' => 2,
            ],
        ], $this->answer());
    }

    public function testAnswersANotificationWithNoContent(): void
    {
        $this->post(['jsonrpc' => '2.0', 'method' => 'diagnostics.log', 'params' => ['hello server']]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', (string) $this->client->getResponse()->getContent());
    }

    /**
     * @param array<array-key, mixed>|string $payload
     */
    #[DataProvider('errors')]
    public function testAnswersWithTheErrorOfTheSpecification(array|string $payload, int $code, string $message): void
    {
        $this->post($payload);

        $this->assertResponseIsSuccessful();
        $answer = $this->answer();
        $this->assertSame(['code' => $code, 'message' => $message], $answer['error'] ?? null);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>|string, int, string}>
     */
    public static function errors(): iterable
    {
        yield 'parse error' => ['{"jsonrpc": "2.0", "method"', -32700, 'Parse error'];
        yield 'unknown method' => [['jsonrpc' => '2.0', 'method' => 'math.pow', 'id' => 1], -32601, 'Method not found'];
        yield 'strings where integers are expected' => [
            ['jsonrpc' => '2.0', 'method' => 'math.add', 'params' => ['5', '3'], 'id' => 1],
            -32602,
            'Invalid params',
        ];
        yield 'batch over the limit' => [
            array_fill(0, 21, ['jsonrpc' => '2.0', 'method' => 'greeting.hello', 'id' => 1]),
            -32600,
            'Invalid Request',
        ];
    }

    public function testRelaysAnApplicationErrorWithItsCodeAndData(): void
    {
        $this->post(['jsonrpc' => '2.0', 'method' => 'math.divide', 'params' => [7, 0], 'id' => 1]);

        $this->assertSame(
            ['code' => 1001, 'message' => 'Division by zero', 'data' => ['dividend' => 7]],
            $this->answer()['error'] ?? null,
        );
    }

    public function testMasksAnInternalError(): void
    {
        $this->post(['jsonrpc' => '2.0', 'method' => 'diagnostics.fail', 'id' => 1]);

        $body = (string) $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString('s3cr3t-db-password', $body);
        $this->assertStringNotContainsString('mysql', $body);
        $this->assertSame(['code' => -32603, 'message' => 'Internal error'], $this->answer()['error'] ?? null);
    }

    public function testRefusesARequestWithoutCredentials(): void
    {
        $this->post(['jsonrpc' => '2.0', 'method' => 'greeting.hello', 'id' => 1], user: null);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertResponseHeaderSame('WWW-Authenticate', 'Basic realm="JsonRPC"');
    }

    public function testRefusesAWrongPassword(): void
    {
        $this->post(['jsonrpc' => '2.0', 'method' => 'greeting.hello', 'id' => 1], password: 'guess');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testKeepsAdminProceduresForTheAdmin(): void
    {
        $this->post(['jsonrpc' => '2.0', 'method' => 'admin.versions', 'id' => 1]);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->post(['jsonrpc' => '2.0', 'method' => 'admin.versions', 'id' => 1], 'admin', 'admin-secret');
        $this->assertResponseIsSuccessful();
        $this->assertSame(PHP_VERSION, $this->answer()['result']['php'] ?? null);
    }

    public function testOnlyAcceptsPost(): void
    {
        $this->client->request('GET', '/rpc');

        $this->assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    /**
     * @param array<array-key, mixed>|string $payload
     */
    private function post(
        array|string $payload,
        ?string $user = 'demo',
        string $password = 'demo-secret',
    ): void {
        $server = ['CONTENT_TYPE' => 'application/json'];

        if ($user !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode($user . ':' . $password);
        }

        $this->client->request(
            'POST',
            '/rpc',
            server: $server,
            content: is_string($payload) ? $payload : json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private function answer(): array
    {
        $answer = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($answer);

        return $answer;
    }
}
