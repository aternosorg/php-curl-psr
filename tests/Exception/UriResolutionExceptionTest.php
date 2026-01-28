<?php

namespace Exception;

use Aternos\CurlPsr\Psr17\Psr17Factory;
use PHPUnit\Framework\TestCase;

class UriResolutionExceptionTest extends TestCase
{
    public function testGetters(): void
    {
        $factory = new Psr17Factory();
        $baseUri = $factory->createUri("https://example.com/base");
        $targetUri = $factory->createUri("https://example.com/target");
        $exception = new \Aternos\CurlPsr\Exception\UriResolutionException(
            $baseUri,
            $targetUri
        );

        $this->assertSame($baseUri, $exception->getBaseUri());
        $this->assertSame($targetUri, $exception->getTargetUri());
    }
}
