<?php

namespace Aternos\CurlPsr\Psr18\UriResolver;

use Aternos\CurlPsr\Exception\UriResolutionException;
use Aternos\CurlPsr\Psr7\Uri;
use InvalidArgumentException;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Throwable;

class UriResolver implements UriResolverInterface
{
    /**
     * @param UriFactoryInterface $uriFactory
     */
    public function __construct(
        protected UriFactoryInterface $uriFactory
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function resolve(UriInterface $baseUri, UriInterface $relativeUri): UriInterface
    {
        if ($baseUri instanceof Uri) {
            $builtInBaseUri = $baseUri->getUri();
        } else {
            $builtInBaseUri = \Uri\Rfc3986\Uri::parse((string)$baseUri);
            if ($builtInBaseUri === null) {
                throw new UriResolutionException($baseUri, $relativeUri, "Could not create built-in URI from base URI");
            }
        }

        try {
            $resolved = $builtInBaseUri->resolve((string)$relativeUri);
        } catch (Throwable $e) {
            throw new UriResolutionException($baseUri, $relativeUri, "Could not resolve URI relative to base URI", previous: $e);
        }

        try {
            return $this->uriFactory->createUri($resolved->toString());
        } catch (InvalidArgumentException $e) {
            throw new UriResolutionException($baseUri, $relativeUri, "Could not create URI from resolved URI string", previous: $e);
        }
    }
}
