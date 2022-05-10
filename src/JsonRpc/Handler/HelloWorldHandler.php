<?php

namespace App\JsonRpc\Handler;

use App\JsonRpc\HandlerInterface;

class HelloWorldHandler implements HandlerInterface
{
    public function getMethodName(): string
    {
        return 'hello_world';
    }

    public function getCallbackName(): string
    {
        return 'handle';
    }

    public function handle(): string
    {
        return 'Hello World!';
    }
}
