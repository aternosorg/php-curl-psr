<?php

namespace Aternos\CurlPsr\Psr18\UriResolver;

use Aternos\CurlPsr\Exception\UriResolutionExceptionInterface;
use Psr\Http\Message\UriInterface;
use Throwable;

interface UriResolverInterface
{
    /**
     * Resolve a relative URI against a base URI
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-5.2
     *
     * @param UriInterface $baseUri
     * @param UriInterface $relativeUri
     * @throws UriResolutionExceptionInterface
     * @return UriInterface
     */
    public function resolve(UriInterface $baseUri, UriInterface $relativeUri): UriInterface;
}
