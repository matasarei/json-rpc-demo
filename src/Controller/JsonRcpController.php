<?php

namespace App\Controller;

use JsonRPC\Server;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class JsonRcpController
{
    public function index(Server $jsonRpcServer): JsonResponse
    {
        return new JsonResponse($jsonRpcServer->execute(), Response::HTTP_OK, [], true);
    }
}
