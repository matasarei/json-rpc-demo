<?php

namespace App\JsonRpc\Handler;

use App\JsonRpc\HandlerInterface;

class ConcatHandler implements HandlerInterface
{
    public function getMethodName(): string
    {
        return 'concat';
    }

    public function getCallbackName(): string
    {
        return 'concat';
    }

    public function concat($a, $b): string
    {
        return sprintf('%s %s', trim($a), trim($b));
    }
}
