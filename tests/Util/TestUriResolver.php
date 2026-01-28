<?php

namespace Tests\Util;

use Aternos\CurlPsr\Psr18\UriResolver\UriResolverInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Throwable;

class TestUriResolver implements UriResolverInterface
{
    protected array $results = [];

    /**
     * @param UriInterface|Throwable $result
     * @return $this
     */
    public function addResult(UriInterface|Throwable $result): static
    {
        $this->results[] = $result;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function resolve(UriInterface $baseUri, UriInterface $relativeUri): UriInterface
    {
        $result = array_shift($this->results);
        if ($result === null) {
            throw new RuntimeException("No more results in TestUriResolver");
        }
        if ($result instanceof Throwable) {
            throw $result;
        }
        return $result;
    }
}
