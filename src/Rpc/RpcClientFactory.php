<?php

declare(strict_types=1);

namespace App\Rpc;

use JsonRPC\Client;
use JsonRPC\HttpClient;
use JsonRPC\Transport\Psr18Transport;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Builds JSON-RPC clients that send their requests through Symfony HttpClient.
 *
 * json-rpc 2.0 accepts any PSR-18 client; Psr18Client adapts the scoped
 * rpc.client service, which also serves as the PSR-17 factories.
 */
final readonly class RpcClientFactory
{
    public function __construct(
        private HttpClientInterface $rpcClient,
        #[Autowire(env: 'RPC_URL')]
        private string $url,
        private LoggerInterface $logger,
    ) {
    }

    public function create(string $username, string $password): Client
    {
        $httpClient = (new HttpClient($this->url, new Psr18Transport(new Psr18Client($this->rpcClient))))
            ->withLogger($this->logger);

        return (new Client($this->url, $httpClient))->authentication($username, $password);
    }
}
