<?php

namespace Tests;

use Aternos\CurlPsr\Psr17\Psr17Factory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

class Psr17FactoryTest extends TestCase
{
    protected Psr17Factory $psr17Factory;
    protected ?string $tmpPath = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->psr17Factory = new Psr17Factory();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->tmpPath && file_exists($this->tmpPath)) {
            unlink($this->tmpPath);
        }
    }

    public function testCreateRequest(): void
    {
        $this->assertInstanceOf(RequestFactoryInterface::class, $this->psr17Factory);
        $request = $this->psr17Factory->createRequest('GET', 'https://example.com');
        $this->assertInstanceOf(RequestInterface::class, $request);
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('https://example.com', (string)$request->getUri());
    }

    public function testCreateResponse(): void
    {
        $this->assertInstanceOf(RequestFactoryInterface::class, $this->psr17Factory);
        $response = $this->psr17Factory->createResponse(200, "OK");
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("OK", $response->getReasonPhrase());
    }

    public function testCreateStream(): void
    {
        $this->assertInstanceOf(StreamFactoryInterface::class, $this->psr17Factory);
        $stream = $this->psr17Factory->createStream('test');
        $this->assertInstanceOf(StreamInterface::class, $stream);
        $this->assertEquals(4, $stream->getSize());
        $this->assertEquals('test', (string)$stream);

        $stream = $this->psr17Factory->createStreamFromFile(__FILE__);
        $this->assertInstanceOf(StreamInterface::class, $stream);
        $this->assertEquals(file_get_contents(__FILE__), (string)$stream);
    }

    public function testCreateStreamFailsOnInvalidMode(): void
    {
        $this->assertInstanceOf(StreamFactoryInterface::class, $this->psr17Factory);

        $this->expectException(InvalidArgumentException::class);
        $this->psr17Factory->createStreamFromFile(__FILE__, "invalid");
    }

    public function testCreateStreamFailsOnFOpenFail(): void
    {
        $this->assertInstanceOf(StreamFactoryInterface::class, $this->psr17Factory);

        $this->expectException(RuntimeException::class);
        $this->psr17Factory->createStreamFromFile(__FILE__ . '/invalid');
    }

    public function testCreateUri(): void
    {
        $string = "https://user:password@example.com/test/path?query=string#fragment";
        $this->assertInstanceOf(UriFactoryInterface::class, $this->psr17Factory);
        $uri = $this->psr17Factory->createUri($string);
        $this->assertInstanceOf(UriInterface::class, $uri);
        $this->assertEquals($string, (string)$uri);
    }

    public function testCreateServerRequest(): void
    {
        $this->assertInstanceOf(ServerRequestFactoryInterface::class, $this->psr17Factory);
        $serverParams = ["Something" => "something"];
        $request = $this->psr17Factory->createServerRequest('GET', 'https://example.com', $serverParams);
        $this->assertInstanceOf(ServerRequestInterface::class, $request);
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('https://example.com', (string)$request->getUri());
        $this->assertEquals($serverParams, $request->getServerParams());
    }

    public function testCreateUploadedFile(): void
    {
        $this->assertInstanceOf(UploadedFileFactoryInterface::class, $this->psr17Factory);
        $this->tmpPath = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($this->tmpPath, 'test');
        $stream = $this->psr17Factory->createStreamFromFile($this->tmpPath);

        $uploadedFile = $this->psr17Factory->createUploadedFile($stream, filesize($this->tmpPath), UPLOAD_ERR_OK, basename($this->tmpPath), 'text/plain');
        $this->assertInstanceOf(UploadedFileInterface::class, $uploadedFile);
    }
}
