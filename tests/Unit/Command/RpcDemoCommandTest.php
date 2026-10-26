<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\RpcDemoCommand;
use App\Rpc\RpcClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(RpcDemoCommand::class)]
final class RpcDemoCommandTest extends TestCase
{
    private const string URL = 'http://rpc.test/rpc';

    public function testFailsWhenTheServerDoesNotAnswerAsExpected(): void
    {
        $tester = $this->tester(new MockHttpClient($this->serverAnsweringEverythingWith('unexpected'), self::URL));

        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('expected "Hello, Ada!", got "unexpected"', $tester->getDisplay());
        $this->assertStringContainsString('scenario(s) did not behave as expected.', $tester->getDisplay());
        $this->assertStringNotContainsString('Every scenario behaved as expected.', $tester->getDisplay());
    }

    private function tester(MockHttpClient $httpClient): CommandTester
    {
        $application = new Application();
        $application->addCommand(new RpcDemoCommand(
            new RpcClientFactory($httpClient, self::URL, new NullLogger()),
            self::URL,
            'demo',
            'demo-secret',
            'admin',
            'admin-secret',
        ));

        return new CommandTester($application->find('app:rpc:demo'));
    }

    /**
     * A server that answers every call, whatever it asks, with the same result.
     *
     * @return callable(string, string, array<string, mixed>): MockResponse
     */
    private function serverAnsweringEverythingWith(string $result): callable
    {
        return static function (string $method, string $url, array $options) use ($result): MockResponse {
            $body = is_string($options['body'] ?? null) ? $options['body'] : '';
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $calls = is_array($payload) && array_is_list($payload) ? $payload : [$payload];

            $answers = [];

            foreach ($calls as $call) {
                if (is_array($call) && array_key_exists('id', $call)) {
                    $answers[] = ['jsonrpc' => '2.0', 'result' => $result, 'id' => $call['id']];
                }
            }

            if ($answers === []) {
                return new MockResponse('', ['http_code' => 204]);
            }

            $isBatch = is_array($payload) && array_is_list($payload);

            return new MockResponse(
                json_encode($isBatch ? $answers : $answers[0], JSON_THROW_ON_ERROR),
                ['response_headers' => ['Content-Type' => 'application/json']],
            );
        };
    }
}
