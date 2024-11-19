<?php

namespace Tests;

use Aternos\CurlPsr\Psr7\Message\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testResponse(): void
    {
        $response = new Response(200, "OK");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("OK", $response->getReasonPhrase());
        $this->assertEquals("1.1", $response->getProtocolVersion());

        $response = $response->withStatus(404, "Not Found");
        $this->assertSame($response, $response->withStatus(404, "Not Found"));
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals("Not Found", $response->getReasonPhrase());
    }
}
