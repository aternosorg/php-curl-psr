<?php

namespace Tests;

use Aternos\CurlPsr\Psr17\Psr17Factory;
use Aternos\CurlPsr\Psr7\Message\ServerRequest;
use Aternos\CurlPsr\Psr7\Uri;
use PHPUnit\Framework\TestCase;

class ServerRequestTest extends TestCase
{
    public function testServerRequest(): void
    {
        $serverParams = ["test" => "value"];
        $request = new ServerRequest("GET", new Uri("https://example.com"), $serverParams);

        $this->assertEquals($serverParams, $request->getServerParams());
        $this->assertEquals([], $request->getCookieParams());
        $request = $request->withCookieParams(["cookie" => "value"]);
        $this->assertEquals(["cookie" => "value"], $request->getCookieParams());
        $this->assertEquals([], $request->getQueryParams());
        $request = $request->withQueryParams(["query" => "value"]);
        $this->assertEquals(["query" => "value"], $request->getQueryParams());
        $this->assertEquals(null, $request->getParsedBody());
        $request = $request->withParsedBody(["parsed" => "body"]);
        $this->assertEquals(["parsed" => "body"], $request->getParsedBody());
        $this->assertEquals([], $request->getAttributes());
        $request = $request->withAttribute("test", "value");
        $this->assertEquals(["test" => "value"], $request->getAttributes());
        $this->assertEquals("value", $request->getAttribute("test"));
        $request = $request->withoutAttribute("test");
        $this->assertEquals([], $request->getAttributes());

        $factory = new Psr17Factory();
        $this->assertEquals([], $request->getUploadedFiles());
        $stream = $factory->createStreamFromFile(__FILE__);
        $uploadedFile = $factory->createUploadedFile($stream, $stream->getSize(), UPLOAD_ERR_OK, "file.txt", "text/plain");
        $request = $request->withUploadedFiles([$uploadedFile]);
        $this->assertEquals([$uploadedFile], $request->getUploadedFiles());
    }
}
