<?php

namespace Tests;

use Aternos\CurlPsr\Psr7\Message\Request;
use Aternos\CurlPsr\Psr7\Uri;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

class RequestTest extends TestCase
{
    protected Request $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new Request('GET', new Uri('https://example.com/test?query=string'));
    }

    public function testRequest(): void
    {
        $this->assertInstanceOf(UriInterface::class, $this->request->getUri());
        $this->assertEquals('GET', $this->request->getMethod());
        $this->assertEquals('https://example.com/test?query=string', (string)$this->request->getUri());
        $this->assertEquals('/test?query=string', $this->request->getRequestTarget());

        $message = $this->request->withMethod('POST');
        $this->assertSame($message, $message->withMethod('POST'));
        $this->assertEquals('POST', $message->getMethod());

        $message = $message->withRequestTarget('/new-target');
        $this->assertSame($message, $message->withRequestTarget('/new-target'));
        $this->assertEquals('/new-target', $message->getRequestTarget());

        $newUri = new Uri('https://example.com/new-target');
        $message = $message->withUri($newUri);
        $this->assertEquals('https://example.com/new-target', (string)$message->getUri());
    }

    public function testHostIsPreservedIfNewUrlHasNoHost(): void
    {
        $newUri = (new Uri('https://example1.com/new-target'))->withHost('');
        $request = $this->request->withUri($newUri);
        $this->assertEquals('example.com', $request->getHeaderLine('Host'));
    }

    public function testHostIsPreservedWithParameter(): void
    {
        $newUri = (new Uri('https://example1.com/new-target'));
        $request = $this->request->withHeader("Host", "example2.com")->withUri($newUri, true);
        $this->assertEquals('example2.com', $request->getHeaderLine('Host'));
    }

    public function testHostIsNotPreservedIfOldUrlHasNoHost(): void
    {
        $request = new Request("GET", (new Uri("https://example1.com/new-target"))->withHost(""));
        $request = $request->withUri(new Uri("https://example2.com/new-target"), true);
        $this->assertEquals("example2.com", $request->getHeaderLine('Host'));
        $this->assertEquals("example2.com", $request->getUri()->getHost());
    }

    public function testChangeHostHeaderIfUriIsChangedAndHostShouldNotBePreserved(): void
    {
        $request = new Request("GET", new Uri("https://example1.com/new-target"));
        $request = $request
            ->withHeader("Host", "example.com")
            ->withUri(new Uri("https://example2.com/new-target"));
        $this->assertEquals("example2.com", $request->getUri()->getHost());
        $this->assertEquals("example2.com", $request->getHeaderLine('Host'));
    }

    #[TestWith(["", "", "", ""], "Example 1")]
    #[TestWith(["", "foo.com", "", "foo.com"], "Example 2")]
    #[TestWith(["", "foo.com", "bar.com", "foo.com"], "Example 3")]
    #[TestWith(["foo.com", "", "bar.com", "foo.com"], "Example 4")]
    #[TestWith(["foo.com", "bar.com", "baz.com", "foo.com"], "Example 5")]
    public function testHostHeaderPsrExamples(string $requestHostHeader, string $requestHostComponent, string $uriHostComponent, string $result): void
    {
        $uri = (new Uri("https://example.com/test"))->withHost($requestHostComponent);

        $request = (new Request("GET", $uri))->withHeader("Host", $requestHostHeader)->withUri($uri, true);

        $uri = $uri->withHost($uriHostComponent);
        $request = $request->withUri($uri, true);
        $this->assertEquals($result, $request->getHeaderLine('Host'));
    }
}
