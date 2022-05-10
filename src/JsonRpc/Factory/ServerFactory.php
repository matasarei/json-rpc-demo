<?php

namespace App\JsonRpc\Factory;

use App\JsonRpc\HandlerCollection;
use JsonRPC\Server;

class ServerFactory
{
    private $handlerCollection;

    public function __construct(HandlerCollection $handlerCollection)
    {
        $this->handlerCollection = $handlerCollection;
    }

    public function createServer(): Server
    {
        $server = new Server();
        $procedureHandler = $server->getProcedureHandler();

        foreach ($this->handlerCollection as $handler) {
            $procedureHandler->withClassAndMethod(
                $handler->getMethodName(),
                $handler,
                $handler->getCallbackName()
            );
        }

        return $server;
    }
}
