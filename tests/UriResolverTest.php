<?php

namespace Tests;

use Aternos\CurlPsr\Exception\UriResolutionExceptionInterface;
use Aternos\CurlPsr\Psr17\Psr17Factory;
use Aternos\CurlPsr\Psr18\UriResolver\UriResolver;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;
use Tests\Util\TestUri;

class UriResolverTest extends TestCase
{
    protected const string BASE_URI = "http://a/b/c/d;p?q";

    #[TestWith(["g:h", "g:h"])]
    #[TestWith(["g", "http://a/b/c/g"])]
    #[TestWith(["./g", "http://a/b/c/g"])]
    #[TestWith(["g/", "http://a/b/c/g/"])]
    #[TestWith(["/g", "http://a/g"])]
    #[TestWith(["//g", "http://g"])]
    #[TestWith(["?y", "http://a/b/c/d;p?y"])]
    #[TestWith(["g?y", "http://a/b/c/g?y"])]
    #[TestWith(["#s", "http://a/b/c/d;p?q#s"])]
    #[TestWith(["g#s", "http://a/b/c/g#s"])]
    #[TestWith(["g?y#s", "http://a/b/c/g?y#s"])]
    #[TestWith([";x", "http://a/b/c/;x"])]
    #[TestWith(["g;x", "http://a/b/c/g;x"])]
    #[TestWith(["g;x?y#s", "http://a/b/c/g;x?y#s"])]
    #[TestWith(["", "http://a/b/c/d;p?q"])]
    #[TestWith([".", "http://a/b/c/"])]
    #[TestWith(["./", "http://a/b/c/"])]
    #[TestWith(["..", "http://a/b/"])]
    #[TestWith(["../", "http://a/b/"])]
    #[TestWith(["../g", "http://a/b/g"])]
    #[TestWith(["../..", "http://a/"])]
    #[TestWith(["../../", "http://a/"])]
    #[TestWith(["../../g", "http://a/g"])]
    #[TestWith(["../../../g", "http://a/g"])]
    #[TestWith(["../../../../g", "http://a/g"])]
    #[TestWith(["abc", "http://a/abc", "http://a"])]
    public function testRelativeResolution(string $relativeUri, string $expectedUri, string $baseUriString = self::BASE_URI): void
    {
        $factory = new Psr17Factory();
        $resolver = new UriResolver($factory);
        $baseUri = $factory->createUri($baseUriString);
        $relativeUri = $factory->createUri($relativeUri);
        $resolvedUri = $resolver->resolve($baseUri, $relativeUri);
        $this->assertEquals($expectedUri, (string)$resolvedUri);
    }

    public function testInvalidBaseUrl(): void
    {
        $factory = new Psr17Factory();
        $base = new TestUri($factory->createUri("https://example.com"))
            ->addHook("__toString", "\\");
        $relative = $factory->createUri("path");
        $resolver = new UriResolver($factory);
        $this->expectException(UriResolutionExceptionInterface::class);
        $this->expectExceptionMessage("Could not create built-in URI from base URI");
        $resolver->resolve($base, $relative);
    }

    public function testInvalidTargetUrl(): void
    {
        $factory = new Psr17Factory();
        $base = $factory->createUri("https://example.com");
        $relative = new TestUri($factory->createUri("path"))
            ->addHook("__toString", "\\");
        $resolver = new UriResolver($factory);
        $this->expectException(UriResolutionExceptionInterface::class);
        $this->expectExceptionMessage("Could not resolve URI relative to base URI");
        $resolver->resolve($base, $relative);
    }

    public function testInvalidFinalUrl(): void
    {
        $factory = new Psr17Factory();
        $base = $factory->createUri("https://example.com");
        $relative = $factory->createUri("path");

        $testFactory = new class extends Psr17Factory {
            public function createUri(string $uri = ""): UriInterface
            {
                throw new InvalidArgumentException("Test exception");
            }
        };

        $resolver = new UriResolver($testFactory);
        $this->expectException(UriResolutionExceptionInterface::class);
        $this->expectExceptionMessage("Could not create URI from resolved URI string");
        $resolver->resolve($base, $relative);
    }
}
