<?php

namespace Tests;

use Aternos\CurlPsr\Exception\CloseStreamException;
use Aternos\CurlPsr\Psr18\ResponseStream;
use Exception;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Tests\Stream\RandomDataStream;

class ResponseStreamTest extends HttpClientTestCase
{
    protected function getTestResponseStream(?StreamInterface $body = null): ResponseStream
    {
        $responseStream = $body ?? $this->streamFactory->createStream("TestTest");
        $this->curlHandle->setResponseBody($responseStream)->setResponseChunkSize(4);

        $size = $responseStream->getSize();
        if ($size !== null) {
            $this->curlHandle->setResponseHeaders([
                'Content-Length: ' . $size
            ]);
        }

        $request = $this->requestFactory->createRequest("GET", "https://example.com");
        $response = $this->client->sendRequest($request);
        /** @var ResponseStream $body */
        $body = $response->getBody();
        return $body;
    }

    public function testResponseStream(): void
    {
        $responseStream = $this->getTestResponseStream();
        $this->assertNull($responseStream->detach());
        $this->assertEquals(8, $responseStream->getSize());
        $this->assertFalse($responseStream->isSeekable());
        $this->assertTrue($responseStream->isReadable());
        $this->assertFalse($responseStream->isWritable());
        $this->assertEquals([], $responseStream->getMetadata());
        $this->assertNull($responseStream->getMetadata("test"));

        $this->assertFalse($responseStream->eof());
        $this->assertEquals(0, $responseStream->tell());
        $this->assertEquals("Test", $responseStream->read(4));
        $this->assertEquals(4, $responseStream->tell());
        $this->assertFalse($responseStream->eof());
        $this->assertEquals("Test", $responseStream->read(4));
        $this->assertEquals(8, $responseStream->tell());
        $this->assertEquals("", $responseStream->read(4));
        $this->assertTrue($responseStream->eof());
    }

    public function testGetSizeReturnsNullIfNoContentLengthHeaderWasSent(): void
    {
        $responseStream = $this->getTestResponseStream(new RandomDataStream(10, false));
        $this->assertNull($responseStream->getSize());
    }

    public function testThrowsOnSeek(): void
    {
        $responseStream = $this->getTestResponseStream();
        $this->expectException(RuntimeException::class);
        $responseStream->seek(0);
    }

    public function testThrowsOnRewind(): void
    {
        $responseStream = $this->getTestResponseStream();
        $this->expectException(RuntimeException::class);
        $responseStream->rewind();
    }

    public function testThrowsOnWrite(): void
    {
        $responseStream = $this->getTestResponseStream();
        $this->expectException(RuntimeException::class);
        $responseStream->write("Test");
    }

    public function testCloseThrowsInWriteFunction(): void
    {
        $responseStream = $this->getTestResponseStream();
        $responseStream->close();
        $this->assertInstanceOf(CloseStreamException::class, $this->curlHandle->getExecError());
    }

    public function testToString(): void
    {
        $responseStream = $this->getTestResponseStream();
        $this->assertEquals("TestTest", (string)$responseStream);
    }

    public function testToStringReturnsEmptyOnError(): void
    {
        $responseStream = $this->getTestResponseStream();
        $this->curlHandle->setErrno(CURLE_COULDNT_CONNECT)->setError("CURLE_COULDNT_CONNECT");
        $this->assertEquals("", (string)$responseStream);
    }

    public function testGetContents(): void
    {
        $responseStream = $this->getTestResponseStream();
        $this->assertEquals("TestTest", $responseStream->getContents());
    }

    public function testGetContentsThrowsOnError(): void
    {
        $responseStream = $this->getTestResponseStream();
        $this->curlHandle->setErrno(CURLE_COULDNT_CONNECT)->setError("CURLE_COULDNT_CONNECT");
        $this->expectException(RuntimeException::class);
        $responseStream->getContents();
    }

    #[TestWith([CURLE_COULDNT_CONNECT, "CURLE_COULDNT_CONNECT"], "CURLE_COULDNT_CONNECT")]
    #[TestWith([CURLE_COULDNT_RESOLVE_HOST, "CURLE_COULDNT_RESOLVE_HOST"], "CURLE_COULDNT_RESOLVE_HOST")]
    #[TestWith([CURLE_COULDNT_RESOLVE_PROXY, "CURLE_COULDNT_RESOLVE_PROXY"], "CURLE_COULDNT_RESOLVE_PROXY")]
    #[TestWith([CURLE_GOT_NOTHING, "CURLE_GOT_NOTHING"], "CURLE_GOT_NOTHING")]
    #[TestWith([CURLE_SSL_CIPHER, "CURLE_SSL_CIPHER"], "CURLE_SSL_CIPHER")]
    #[TestWith([CURLE_ABORTED_BY_CALLBACK, "CURLE_ABORTED_BY_CALLBACK"], "CURLE_ABORTED_BY_CALLBACK")]
    #[TestWith([CURLE_BAD_CONTENT_ENCODING, "CURLE_BAD_CONTENT_ENCODING"], "CURLE_BAD_CONTENT_ENCODING")]
    #[TestWith([CURLE_BAD_DOWNLOAD_RESUME, "CURLE_BAD_DOWNLOAD_RESUME"], "CURLE_BAD_DOWNLOAD_RESUME")]
    public function testThrowsRuntimeExceptionOnCurlErrorDuringResponseStream(int $errno, string $errorMessage): void
    {
        $responseStream = $this->streamFactory->createStream("TestTest");
        $this->curlHandle->setResponseBody($responseStream);

        $request = $this->requestFactory->createRequest("GET", "https://example.com");
        $response = $this->client->sendRequest($request);
        $body = $response->getBody();

        $this->assertEquals("Test", $body->read(4));

        $this->curlHandle->setErrno($errno)->setError($errorMessage);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($errorMessage);
        $body->read(4);
    }

    public function testResponseStreamReadFailsIfFiberResumeThrows(): void
    {
        $responseStream = $this->streamFactory->createStream("TestTest");
        $this->curlHandle
            ->setResponseBody($responseStream)
            ->setResponseChunkSize(4)
            ->setOnAfterWrite(function () {
                throw new Exception("This is an error");
            });

        $request = $this->requestFactory->createRequest("GET", "https://example.com");
        $response = $this->client->sendRequest($request);
        $body = $response->getBody();

        $this->assertEquals("Test", $body->read(4));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Failed to read from stream");
        $body->read(4);
    }
}
