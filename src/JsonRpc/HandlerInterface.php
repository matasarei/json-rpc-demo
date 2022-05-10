<?php

namespace App\JsonRpc;

interface HandlerInterface
{
    public function getMethodName(): string;
    public function getCallbackName(): string;
}
