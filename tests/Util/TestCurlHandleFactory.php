<?php

namespace Tests\Util;

use Aternos\CurlPsr\Curl\CurlHandleFactoryInterface;
use Aternos\CurlPsr\Curl\CurlHandleInterface;
use Exception;

class TestCurlHandleFactory implements CurlHandleFactoryInterface
{
    protected array $next = [];

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function createCurlHandle(): CurlHandleInterface
    {
        if (count($this->next) === 0) {
            throw new Exception("No curl handle available");
        }
        return array_shift($this->next);
    }

    /**
     * @return TestCurlHandle
     */
    public function nextTestHandle(): TestCurlHandle
    {
        $handle = new TestCurlHandle();
        $this->next[] = $handle;
        return $handle;
    }
}
