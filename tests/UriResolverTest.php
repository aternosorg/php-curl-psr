<?php

namespace Tests;

use Aternos\CurlPsr\Psr17\Psr17Factory;
use Aternos\CurlPsr\Psr18\UriResolver\UriResolver;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

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
    public function testRelativeResolution(string $relativeUri, string $expectedUri)
    {
        $factory = new Psr17Factory();
        $resolver = new UriResolver($factory);
        $baseUri = $factory->createUri(self::BASE_URI);
        $relativeUri = $factory->createUri($relativeUri);
        $resolvedUri = $resolver->resolve($baseUri, $relativeUri);
        $this->assertEquals($expectedUri, (string)$resolvedUri);
    }
}
