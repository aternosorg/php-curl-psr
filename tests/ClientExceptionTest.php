<?php

namespace Tests;

use Aternos\CurlPsr\Exception\NetworkException;
use Aternos\CurlPsr\Exception\RequestException;

class ClientExceptionTest extends HttpClientTestCase
{
    public function testNetworkException(): void
    {
        $request = $this->requestFactory->createRequest("GET", "https://example.com");
        $message = "Test";
        $code = 123;
        $e = new NetworkException($request, $message, $code);

        $this->assertSame($request, $e->getRequest());
        $this->assertSame($message, $e->getMessage());
        $this->assertSame($code, $e->getCode());
    }

    public function testRequestException(): void
    {
        $request = $this->requestFactory->createRequest("GET", "https://example.com");
        $message = "Test";
        $code = 123;
        $e = new RequestException($request, $message, $code);

        $this->assertSame($request, $e->getRequest());
        $this->assertSame($message, $e->getMessage());
        $this->assertSame($code, $e->getCode());
    }
}
