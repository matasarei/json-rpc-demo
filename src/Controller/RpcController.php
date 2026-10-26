<?php

declare(strict_types=1);

namespace App\Controller;

use JsonRPC\Server;
use JsonRPC\Server\ServerRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The JSON-RPC endpoint: the Symfony request in, the server's answer out.
 */
#[AsController]
final readonly class RpcController
{
    public function __construct(private Server $server)
    {
    }

    #[Route('/rpc', name: 'rpc', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $rpcResponse = $this->server->execute(ServerRequest::fromString($request->getContent(), [
            ...$request->server->all(),
            // Symfony decodes the Authorization header itself, so the credentials
            // arrive the same way whatever the web server puts in PHP_AUTH_*.
            'PHP_AUTH_USER' => $request->getUser(),
            'PHP_AUTH_PW' => $request->getPassword(),
        ]));

        // The status carries 204, 401 and 403 answers; the headers carry
        // Content-Type and WWW-Authenticate.
        return new Response($rpcResponse->body, $rpcResponse->statusCode, $rpcResponse->headers);
    }
}
