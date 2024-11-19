<?php

namespace Tests;

use Aternos\CurlPsr\Psr17\Psr17Factory;
use Aternos\CurlPsr\Psr18\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Tests\Util\TestCurlHandle;
use Tests\Util\TestCurlHandleFactory;

class HttpClientTestCase extends TestCase
{
    protected Client $client;
    protected TestCurlHandle $curlHandle;
    protected TestCurlHandleFactory $curlHandleFactory;
    protected RequestFactoryInterface $requestFactory;
    protected StreamFactoryInterface $streamFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $psrFactory = new Psr17Factory();
        $this->requestFactory = $psrFactory;
        $this->streamFactory = $psrFactory;

        $this->curlHandleFactory = new TestCurlHandleFactory();
        $this->curlHandle = $this->curlHandleFactory->nextTestHandle();
        $this->client = new Client($psrFactory, $this->curlHandleFactory);
    }
}
