<?php

namespace App\JsonRpc;

use Iterator;

class HandlerCollection implements Iterator
{
    /**
     * @var HandlerInterface[]
     */
    private $collection;

    public function __construct()
    {
        $this->collection = [];
    }

    public function addHandler(HandlerInterface $handler): self
    {
        $this->collection[$handler->getMethodName()] = $handler;

        return $this;
    }

    public function valid()
    {
        return $this->current() !== false;
    }

    public function next()
    {
        next($this->collection);
    }

    public function current()
    {
        return current($this->collection);
    }

    public function rewind()
    {
        reset($this->collection);
    }

    public function key()
    {
        return key($this->collection);
    }
}
