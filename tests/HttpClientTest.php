<?php

namespace Tests;

use PHPUnit\Framework\Attributes\TestWith;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\Stream\HashWriteStream;
use Tests\Stream\PredefinedChunkStream;
use Tests\Stream\RandomDataStream;

class HttpClientTest extends HttpClientTestCase
{
    public function testLargeGet(): void
    {
        $responseBody = new RandomDataStream(128 * 1024 * 1024);
        $requestBodySink = $this->streamFactory->createStreamFromFile("php://memory", "r+");

        $this->curlHandle
            ->setResponseHeaders([
                'Content-Type: application/octet-stream',
                'Content-Length: ' . $responseBody->getSize()
            ])
            ->setRequestBodySink($requestBodySink)
            ->setResponseBody($responseBody);

        $request = $this->requestFactory->createRequest("GET", "https://example.com");
        $response = $this->client->sendRequest($request);

        $this->assertEquals("GET", $this->curlHandle->getOption(CURLOPT_CUSTOMREQUEST));
        $this->assertEquals("/", $this->curlHandle->getOption(CURLOPT_REQUEST_TARGET));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("TEST", $response->getReasonPhrase());
        $this->assertEquals("1.1", $response->getProtocolVersion());
        $this->assertEquals("application/octet-stream", $response->getHeaderLine("Content-Type"));
        $this->assertEquals(strval($responseBody->getSize()), $response->getHeaderLine("Content-Length"));
        $this->assertEmpty($requestBodySink->__toString());

        $responseHash = HashWriteStream::readFrom($response->getBody());
        $this->assertEquals($responseBody->getFinalHash(), $responseHash);
    }

    public function testLargePut(): void
    {
        $responseBody = $this->streamFactory->createStream("Success");
        $requestBody = new RandomDataStream(128 * 1024 * 1024);
        $requestBodySink = new HashWriteStream();

        $this->curlHandle
            ->setResponseHeaders([
                'Content-Type: text/plain',
                'Content-Length: ' . $responseBody->getSize()
            ])
            ->setRequestBodySink($requestBodySink)
            ->setResponseBody($responseBody);

        $request = $this->requestFactory->createRequest("PUT", "https://example.com/put?test=1")
            ->withHeader("Content-Type", "application/octet-stream")
            ->withBody($requestBody);

        $response = $this->client->sendRequest($request);

        $this->assertEquals("PUT", $this->curlHandle->getOption(CURLOPT_CUSTOMREQUEST));
        $this->assertEquals("/put?test=1", $this->curlHandle->getOption(CURLOPT_REQUEST_TARGET));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("TEST", $response->getReasonPhrase());
        $this->assertEquals("1.1", $response->getProtocolVersion());
        $this->assertEquals("text/plain", $response->getHeaderLine("Content-Type"));
        $this->assertEquals("Success", $response->getBody()->getContents());
        $this->assertEquals(strval($responseBody->getSize()), $response->getHeaderLine("Content-Length"));
        $this->assertEquals(strval($requestBody->getSize()), $this->curlHandle->getRequestHeader("Content-Length"));
        $this->assertEquals($requestBody->getFinalHash(), $requestBodySink->getFinalHash());
    }

    public function testHead(): void
    {
        $request = $this->requestFactory->createRequest("HEAD", "https://example.com");
        $response = $this->client->sendRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("TEST", $response->getReasonPhrase());
        $this->assertEquals("HEAD", $this->curlHandle->getOption(CURLOPT_CUSTOMREQUEST));
    }

    public function testCustom(): void
    {
        $request = $this->requestFactory->createRequest("CUSTOM", "https://example.com");
        $response = $this->client->sendRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("TEST", $response->getReasonPhrase());
        $this->assertEquals("CUSTOM", $this->curlHandle->getOption(CURLOPT_CUSTOMREQUEST));
    }

    public function testEmptyReasonPhrase(): void
    {
        $this->curlHandle->setResponseStatusLine("HTTP/1.1 200");
        $request = $this->requestFactory->createRequest("GET", "https://example.com");
        $response = $this->client->sendRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("", $response->getReasonPhrase());
        $this->assertEquals("GET", $this->curlHandle->getOption(CURLOPT_CUSTOMREQUEST));
    }

    #[TestWith(["1.0", CURL_HTTP_VERSION_1_0])]
    #[TestWith(["1.1", CURL_HTTP_VERSION_1_1])]
    #[TestWith(["2.0", CURL_HTTP_VERSION_2_0])]
    public function testHttpProtocolVersions(string $versionString, int $curlValue): void
    {
        $request = $this->requestFactory->createRequest("GET", "https://example.com")
            ->withProtocolVersion($versionString);
        $this->client->sendRequest($request);

        $this->assertEquals($curlValue, $this->curlHandle->getOption(CURLOPT_HTTP_VERSION));
    }

    public function testRemoveEncodingAndLengthHeaderForEncodedResponse(): void
    {
        $this->curlHandle->setResponseHeaders([
            "Content-Encoding: gzip",
            "Content-Length: 100"
        ]);

        $request = $this->requestFactory->createRequest("GET", "https://example.com");
        $response = $this->client->sendRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($response->hasHeader("Content-Encoding"));
        $this->assertFalse($response->hasHeader("Content-Length"));
    }

    public function testThrowRequestExceptionIfRequestBodyThrows(): void
    {
        $requestBody = new PredefinedChunkStream([
            "something",
            new RuntimeException("Something went wrong")
        ]);

        $request = $this->requestFactory->createRequest("POST", "https://example.com")
            ->withBody($requestBody);

        $this->expectException(RequestExceptionInterface::class);
        $this->expectExceptionMessage("Something went wrong");
        $this->client->sendRequest($request);
    }

    public function testRequestBodyStreamCanReturnEmptyChunks(): void
    {
        $requestBody = new PredefinedChunkStream([
            "something",
            "",
            "else"
        ]);

        $chunks = [];
        $this->curlHandle->setOnAfterRead(function ($chunk) use (&$chunks) {
            $chunks[] = $chunk;
        });

        $request = $this->requestFactory->createRequest("POST", "https://example.com")
            ->withBody($requestBody);

        $this->client->sendRequest($request);

        // Last chunk is empty
        $this->assertEmpty(array_pop($chunks));

        // No empty chunks in between
        foreach ($chunks as $chunk) {
            $this->assertNotEmpty($chunk);
        }
    }

    #[TestWith([CURLE_COULDNT_CONNECT, "CURLE_COULDNT_CONNECT"], "CURLE_COULDNT_CONNECT")]
    #[TestWith([CURLE_COULDNT_RESOLVE_HOST, "CURLE_COULDNT_RESOLVE_HOST"], "CURLE_COULDNT_RESOLVE_HOST")]
    #[TestWith([CURLE_COULDNT_RESOLVE_PROXY, "CURLE_COULDNT_RESOLVE_PROXY"], "CURLE_COULDNT_RESOLVE_PROXY")]
    #[TestWith([CURLE_GOT_NOTHING, "CURLE_GOT_NOTHING"], "CURLE_GOT_NOTHING")]
    #[TestWith([CURLE_OPERATION_TIMEDOUT, "CURLE_OPERATION_TIMEDOUT"], "CURLE_OPERATION_TIMEDOUT")]
    #[TestWith([CURLE_OPERATION_TIMEOUTED, "CURLE_OPERATION_TIMEOUTED"], "CURLE_OPERATION_TIMEOUTED")]
    #[TestWith([CURLE_RECV_ERROR, "CURLE_RECV_ERROR"], "CURLE_RECV_ERROR")]
    #[TestWith([CURLE_SEND_ERROR, "CURLE_SEND_ERROR"], "CURLE_SEND_ERROR")]
    public function testThrowNetworkExceptionOnCurlNetworkError(int $errno, string $errorMessage): void
    {
        $this->curlHandle->setErrno($errno)->setError($errorMessage);
        $request = $this->requestFactory->createRequest("GET", "https://example.com");

        $this->expectException(NetworkExceptionInterface::class);
        $this->expectExceptionMessage($errorMessage);
        $this->client->sendRequest($request);
    }

    #[TestWith([CURLE_SSL_CONNECT_ERROR, "CURLE_SSL_CONNECT_ERROR"], "CURLE_SSL_CONNECT_ERROR")]
    #[TestWith([CURLE_SSL_CACERT, "CURLE_SSL_CACERT"], "CURLE_SSL_CACERT")]
    #[TestWith([CURLE_SSL_CACERT_BADFILE, "CURLE_SSL_CACERT_BADFILE"], "CURLE_SSL_CACERT_BADFILE")]
    #[TestWith([CURLE_SSL_CERTPROBLEM, "CURLE_SSL_CERTPROBLEM"], "CURLE_SSL_CERTPROBLEM")]
    #[TestWith([CURLE_SSL_CIPHER, "CURLE_SSL_CIPHER"], "CURLE_SSL_CIPHER")]
    #[TestWith([CURLE_ABORTED_BY_CALLBACK, "CURLE_ABORTED_BY_CALLBACK"], "CURLE_ABORTED_BY_CALLBACK")]
    #[TestWith([CURLE_BAD_CONTENT_ENCODING, "CURLE_BAD_CONTENT_ENCODING"], "CURLE_BAD_CONTENT_ENCODING")]
    #[TestWith([CURLE_BAD_DOWNLOAD_RESUME, "CURLE_BAD_DOWNLOAD_RESUME"], "CURLE_BAD_DOWNLOAD_RESUME")]
    public function testThrowRequestExceptionOnOtherCurlError(int $errno, string $errorMessage): void
    {
        $this->curlHandle->setErrno($errno)->setError($errorMessage);
        $request = $this->requestFactory->createRequest("GET", "https://example.com");

        $this->expectException(RequestExceptionInterface::class);
        $this->expectExceptionMessage($errorMessage);
        $this->client->sendRequest($request);
    }

    public function testThrowsRequestExceptionOnMissingStatusLine(): void
    {
        $this->curlHandle->setResponseStatusLine("");

        $request = $this->requestFactory->createRequest("GET", "https://example.com");
        $this->expectException(RequestExceptionInterface::class);
        $this->expectExceptionMessage("Invalid response");
        $this->client->sendRequest($request);
    }

    public function testProgressCallbackHasTotalSizeIfKnown(): void
    {
        $requestBody = new RandomDataStream(rand(1, 1024 * 1024));
        $responseBody = new RandomDataStream(rand(1, 1024 * 1024));

        $this->curlHandle
            ->setResponseBody($responseBody)
            ->setResponseHeaders([
                "Content-Length: " . $responseBody->getSize()
            ]);

        $dlTotal = null;
        $uTotal = null;
        $this->client->setProgressCallback(function (RequestInterface $request, int $downloadTotal, int $dl, int $uploadTotal, int $u) use (&$dlTotal, &$uTotal) {
            if ($downloadTotal) {
                $dlTotal = $downloadTotal;
            }
            if ($uploadTotal) {
                $uTotal = $uploadTotal;
            }
        });

        $request = $this->requestFactory->createRequest("POST", "https://example.com")
            ->withBody($requestBody);
        $response = $this->client->sendRequest($request);
        $response->getBody()->getContents();

        $this->assertEquals($requestBody->getSize(), $uTotal);
        $this->assertEquals($responseBody->getSize(), $dlTotal);
    }

    public function testProgressCallbackHasNoTotalSizeIfUnknwon(): void
    {
        $requestBody = new RandomDataStream(rand(1, 1024 * 1024), false);
        $responseBody = new RandomDataStream(rand(1, 1024 * 1024), false);

        $this->curlHandle
            ->setResponseBody($responseBody);

        $dlTotal = null;
        $uTotal = null;
        $this->client->setProgressCallback(function (RequestInterface $request, int $downloadTotal, int $dl, int $uploadTotal, int $u) use (&$dlTotal, &$uTotal) {
            if ($downloadTotal) {
                $dlTotal = $downloadTotal;
            }
            if ($uploadTotal) {
                $uTotal = $uploadTotal;
            }
        });

        $request = $this->requestFactory->createRequest("POST", "https://example.com")
            ->withBody($requestBody);
        $response = $this->client->sendRequest($request);
        $response->getBody()->getContents();

        $this->assertNull($uTotal);
        $this->assertNull($dlTotal);
    }

    public function testFallbackToProgressFunctionIfXferInfoFunctionIsMissing(): void
    {
        $requestBody = new RandomDataStream(32);
        $responseBody = new RandomDataStream(32);

        $this->curlHandle
            ->setResponseBody($responseBody);

        $this->client = new class($this->requestFactory, $this->curlHandleFactory) extends \Aternos\CurlPsr\Psr18\Client {
            protected function shouldUseXferInfoFunction(): bool
            {
                return false;
            }
        };

        $progressCalled = false;
        $this->client->setProgressCallback(function () use (&$progressCalled) {
            $progressCalled = true;
        });

        $request = $this->requestFactory->createRequest("POST", "https://example.com")
            ->withBody($requestBody);
        $response = $this->client->sendRequest($request);
        $response->getBody()->getContents();

        $this->assertArrayHasKey(CURLOPT_PROGRESSFUNCTION, $this->curlHandle->getOptions());
        $this->assertTrue($progressCalled);
    }

    public function testSendEmptyHeader(): void
    {
        $request = $this->requestFactory->createRequest("GET", "https://example.com")
            ->withHeader("X-Test", "");
        $this->client->sendRequest($request);

        $this->assertContains("X-Test;", $this->curlHandle->getOption(CURLOPT_HTTPHEADER));
    }

    public function testTimeout(): void
    {
        $this->client->setTimeout(10);
        $this->assertEquals(10, $this->client->getTimeout());

        $request = $this->requestFactory->createRequest("GET", "https://example.com");
        $this->client->sendRequest($request);

        $this->assertEquals(10, $this->curlHandle->getOption(CURLOPT_TIMEOUT));
    }

    public function testMaxRedirects(): void
    {
        $this->client->setMaxRedirects(10);
        $this->assertEquals(10, $this->client->getMaxRedirects());

        $request = $this->requestFactory->createRequest("GET", "https://example.com");
        $this->client->sendRequest($request);

        $this->assertTrue($this->curlHandle->getOption(CURLOPT_FOLLOWLOCATION));
        $this->assertEquals(10, $this->curlHandle->getOption(CURLOPT_MAXREDIRS));
    }

    public function testDisableRedirects(): void
    {
        $this->client->setMaxRedirects(0);
        $this->assertEquals(0, $this->client->getMaxRedirects());

        $request = $this->requestFactory->createRequest("GET", "https://example.com");
        $this->client->sendRequest($request);

        $this->assertFalse($this->curlHandle->getOption(CURLOPT_FOLLOWLOCATION));
        $this->assertEquals(0, $this->curlHandle->getOption(CURLOPT_MAXREDIRS));
    }

    public function testCookieFile(): void
    {
        $this->client->setCookieFile("cookie.txt");
        $this->assertEquals("cookie.txt", $this->client->getCookieFile());

        $request = $this->requestFactory->createRequest("GET", "https://example.com");
        $this->client->sendRequest($request);

        $this->assertEquals("cookie.txt", $this->curlHandle->getOption(CURLOPT_COOKIEFILE));
    }

    public function testCustomCurlOption(): void
    {
        $this->client->setCurlOption(CURLOPT_BUFFERSIZE, 1024);
        $this->assertEquals(1024, $this->client->getCurlOption(CURLOPT_BUFFERSIZE));

        $request = $this->requestFactory->createRequest("GET", "https://example.com");
        $this->client->sendRequest($request);

        $this->assertEquals(1024, $this->curlHandle->getOption(CURLOPT_BUFFERSIZE));
    }

    public function testDefaultHeader(): void
    {
        $defaultHeaders = [
            "X-Test" => ["Test"]
        ];
        $this->client->setDefaultHeaders($defaultHeaders);
        $this->assertEquals($defaultHeaders, $this->client->getDefaultHeaders());

        $this->client->addDefaultHeader("X-Test-2", "Test");
        $this->assertEquals([
            "X-Test" => ["Test"],
            "X-Test-2" => ["Test"]
        ], $this->client->getDefaultHeaders());
        $this->client->addDefaultHeader("X-Test-2", "Test2");
        $this->assertEquals([
            "X-Test" => ["Test"],
            "X-Test-2" => ["Test2"]
        ], $this->client->getDefaultHeaders());

        $request = $this->requestFactory->createRequest("GET", "https://example.com");
        $this->client->sendRequest($request);

        $this->assertContains("X-Test: Test", $this->curlHandle->getOption(CURLOPT_HTTPHEADER));
        $this->assertContains("X-Test-2: Test2", $this->curlHandle->getOption(CURLOPT_HTTPHEADER));
    }

    public function testDefaultHeaderOverwrittenByRequestHeader(): void
    {
        $defaultHeaders = [
            "X-Test" => ["Test"]
        ];
        $this->client->setDefaultHeaders($defaultHeaders);
        $this->assertEquals($defaultHeaders, $this->client->getDefaultHeaders());

        $request = $this->requestFactory->createRequest("GET", "https://example.com")
            ->withHeader("X-Test", "Request");
        $this->client->sendRequest($request);

        $this->assertContains("X-Test: Request", $this->curlHandle->getOption(CURLOPT_HTTPHEADER));
        $this->assertNotContains("X-Test: Test", $this->curlHandle->getOption(CURLOPT_HTTPHEADER));
    }
}
