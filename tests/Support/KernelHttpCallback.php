<?php

declare(strict_types=1);

namespace App\Tests\Support;

use LogicException;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Answers MockHttpClient requests with the application kernel.
 *
 * In the test environment the rpc.client service is a MockHttpClient using
 * this callback, so the real JSON-RPC client reaches the real controller
 * without a web server or a socket.
 */
final readonly class KernelHttpCallback
{
    public function __construct(private HttpKernelInterface $kernel)
    {
    }

    /**
     * @param array<string, mixed> $options Options as MockHttpClient normalizes them
     */
    public function __invoke(string $method, string $url, array $options): MockResponse
    {
        $body = $options['body'] ?? '';

        if (!is_string($body)) {
            throw new LogicException('Only request bodies given as a string are supported');
        }

        $response = $this->kernel->handle(Request::create(
            $url,
            $method,
            server: $this->serverVariables($options['headers'] ?? []),
            content: $body,
        ));

        return new MockResponse((string) $response->getContent(), [
            'http_code' => $response->getStatusCode(),
            'response_headers' => $response->headers->allPreserveCase(),
        ]);
    }

    /**
     * @param mixed $headers Header lines, "Name: value"
     *
     * @return array<string, string>
     */
    private function serverVariables(mixed $headers): array
    {
        $server = [];

        foreach (is_array($headers) ? $headers : [] as $line) {
            if (!is_string($line)) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2) + [1 => ''];
            $name = strtoupper(str_replace('-', '_', trim($name)));
            $key = in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $name : 'HTTP_' . $name;
            $server[$key] = trim($value);
        }

        return $server;
    }
}
