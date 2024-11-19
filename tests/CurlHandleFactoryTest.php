<?php

namespace Tests;

use Aternos\CurlPsr\Curl\CurlHandleFactory;
use Aternos\CurlPsr\Curl\CurlHandleInterface;
use PHPUnit\Framework\TestCase;

class CurlHandleFactoryTest extends TestCase
{
    public function testCurlHandleFactory(): void
    {
        $factory = new CurlHandleFactory();
        $handle = $factory->createCurlHandle();
        $this->assertInstanceOf(CurlHandleInterface::class, $handle);
    }
}
