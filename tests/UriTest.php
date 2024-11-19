<?php

namespace Tests;

use Aternos\CurlPsr\Psr7\Uri;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UriTest extends TestCase
{
    protected string $uriString = "https://user:password@example.com:123/test/path?query=string#fragment";
    protected Uri $uri;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uri = new Uri($this->uriString);
    }

    public function testUriComponents(): void
    {
        $this->assertEquals("https", $this->uri->getScheme());
        $this->assertEquals("user:password", $this->uri->getUserInfo());
        $this->assertEquals("example.com", $this->uri->getHost());
        $this->assertEquals(123, $this->uri->getPort());
        $this->assertEquals("/test/path", $this->uri->getPath());
        $this->assertEquals("query=string", $this->uri->getQuery());
        $this->assertEquals("fragment", $this->uri->getFragment());
        $this->assertEquals("user:password@example.com:123", $this->uri->getAuthority());
        $this->assertEquals($this->uriString, (string)$this->uri);
    }

    public function testThrowsOnInvalidUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Uri("http://");
    }

    public function testThrowsOnInvalidPort(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->uri->withPort(-1);
    }

    public function testHidesDefaultPort(): void
    {
        $uri = new Uri("http://example.com:80");
        $this->assertEquals("http://example.com", (string)$uri);

        $uri = new Uri("https://example.com:443");
        $this->assertEquals("https://example.com", (string)$uri);
    }

    public function testUpdateUrl(): void
    {
        $uri = $this->uri->withScheme("http");
        $this->assertEquals("http", $uri->getScheme());

        $uri = $uri->withUserInfo("newuser", "newpassword");
        $this->assertEquals("newuser:newpassword", $uri->getUserInfo());

        $uri = $uri->withUserInfo("newuser", "");
        $this->assertEquals("newuser", $uri->getUserInfo());

        $uri = $uri->withUserInfo("newuser");
        $this->assertEquals("newuser", $uri->getUserInfo());

        $uri = $uri->withUserInfo("");
        $this->assertEquals("", $uri->getUserInfo());

        $uri = $uri->withHost("newhost.com");
        $this->assertEquals("newhost.com", $uri->getHost());

        $uri = $uri->withPort(456);
        $this->assertEquals(456, $uri->getPort());

        $uri = $uri->withPath("/new/path");
        $this->assertEquals("/new/path", $uri->getPath());

        $uri = $uri->withQuery("newquery=string");
        $this->assertEquals("newquery=string", $uri->getQuery());

        $uri = $uri->withFragment("newfragment");
        $this->assertEquals("newfragment", $uri->getFragment());

        $this->assertEquals("http://newhost.com:456/new/path?newquery=string#newfragment", (string)$uri);

        $this->assertSame($uri, $uri->withScheme($uri->getScheme()));
        $this->assertSame($uri, $uri->withUserInfo(""));
        $this->assertSame($uri, $uri->withHost($uri->getHost()));
        $this->assertSame($uri, $uri->withPort($uri->getPort()));
        $this->assertSame($uri, $uri->withPath($uri->getPath()));
        $this->assertSame($uri, $uri->withQuery($uri->getQuery()));
        $this->assertSame($uri, $uri->withFragment($uri->getFragment()));
    }

    public function testPathStartsWithSlashIfAuthorityIsSet(): void
    {
        $uri = $this->uri->withPath("test");
        $this->assertEquals("https://user:password@example.com:123/test?query=string#fragment", (string)$uri);
    }

    public function testRemoveDuplicateStartSlashesIfAuthorityIsMissing(): void
    {
        $uri = $this->uri
            ->withHost("")
            ->withPort(443)
            ->withUserInfo("")
            ->withPath("///test/path");
        $this->assertEquals("https:/test/path?query=string#fragment", (string)$uri);
    }

    public function testDoesNotDoubleEncode(): void
    {
        $uri = $this->uri->withPath("/baz%2F");
        $this->assertEquals("/baz%2F", $uri->getPath());
    }

    public function testRemovePort(): void
    {
        $uri = $this->uri->withPort(null);
        $this->assertEquals(null, $uri->getPort());
    }

    public function testThrowOnInvalidScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->uri->withScheme("1http");
    }

    public function testUseIpv4Host(): void
    {
        $uri = $this->uri->withHost("127.0.0.1");
        $this->assertEquals("127.0.0.1", $uri->getHost());
        $this->assertEquals("https://user:password@127.0.0.1:123/test/path?query=string#fragment", (string)$uri);
    }

    public function testUseIpv6Host(): void
    {
        $uri = $this->uri->withHost("[::1]");
        $this->assertEquals("[::1]", $uri->getHost());
        $this->assertEquals("https://user:password@[::1]:123/test/path?query=string#fragment", (string)$uri);

        $this->expectException(InvalidArgumentException::class);
        $uri->withHost("[127.0.0.1]");
    }
}
