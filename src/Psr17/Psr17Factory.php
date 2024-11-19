<?php

namespace Aternos\CurlPsr\Psr17;

use Aternos\CurlPsr\Psr7\Message\Request;
use Aternos\CurlPsr\Psr7\Message\Response;
use Aternos\CurlPsr\Psr7\Message\ServerRequest;
use Aternos\CurlPsr\Psr7\Message\UploadedFile;
use Aternos\CurlPsr\Psr7\Stream\Stream;
use Aternos\CurlPsr\Psr7\Stream\StringStream;
use Aternos\CurlPsr\Psr7\Uri;
use InvalidArgumentException;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
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
use const UPLOAD_ERR_OK;

class Psr17Factory implements StreamFactoryInterface, RequestFactoryInterface, UriFactoryInterface, ResponseFactoryInterface, ServerRequestFactoryInterface, UploadedFileFactoryInterface
{

    /**
     * @inheritDoc
     */
    public function createStream(string $content = ''): StreamInterface
    {
        return new StringStream($content);
    }

    /**
     * @inheritDoc
     */
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        $resource = @fopen($filename, $mode);
        if ($resource === false) {
            if (strlen($mode) === 0 || !in_array($mode[0], ['r', 'w', 'a', 'x', 'c'])) {
                throw new InvalidArgumentException("Invalid mode " . $mode);
            }
            throw new RuntimeException("Could not open file " . $filename);
        }

        return $this->createStreamFromResource($resource);
    }

    /**
     * @inheritDoc
     */
    public function createStreamFromResource($resource): StreamInterface
    {
        return new Stream($resource);
    }

    /**
     * @inheritDoc
     */
    public function createRequest(string $method, $uri): RequestInterface
    {
        return new Request($method, $this->createUri($uri));
    }

    /**
     * @inheritDoc
     */
    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }

    /**
     * @inheritDoc
     */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response($code, $reasonPhrase);
    }

    /**
     * @inheritDoc
     */
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        return new ServerRequest($method, $this->createUri($uri), $serverParams);
    }

    /**
     * @inheritDoc
     */
    public function createUploadedFile(
        StreamInterface $stream,
        ?int            $size = null,
        int             $error = UPLOAD_ERR_OK,
        ?string         $clientFilename = null,
        ?string         $clientMediaType = null
    ): UploadedFileInterface
    {
        return new UploadedFile($stream, $size, $error, $clientFilename, $clientMediaType);
    }
}
