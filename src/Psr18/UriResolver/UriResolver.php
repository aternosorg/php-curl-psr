<?php

namespace Aternos\CurlPsr\Psr18\UriResolver;

use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

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
        if ($relativeUri->getScheme() !== "") {
            return $relativeUri;
        }

        if ($relativeUri->getAuthority() !== "") {
            return $relativeUri
                ->withPath($this->removeDotSegments($relativeUri->getPath()))
                ->withScheme($baseUri->getScheme());
        }

        $result = $this->uriFactory->createUri();
        if ($relativeUri->getPath() === "") {
            $result = $result->withPath($baseUri->getPath());
            if ($relativeUri->getQuery() !== "") {
                $result = $result->withQuery($relativeUri->getQuery());
            } else {
                $result = $result->withQuery($baseUri->getQuery());
            }
        } else {
            $path = $this->mergePaths($baseUri->getPath(), $relativeUri->getPath());
            $result = $result->withPath($this->removeDotSegments($path))
                ->withQuery($relativeUri->getQuery());
        }
        $result = $result->withUserInfo($baseUri->getUserInfo())
            ->withHost($baseUri->getHost())
            ->withPort($baseUri->getPort());

        return $result->withScheme($baseUri->getScheme())
            ->withFragment($relativeUri->getFragment());
    }

    /**
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-5.2.3
     * @param string $base
     * @param string $relative
     * @return string
     */
    protected function mergePaths(string $base, string $relative): string
    {
        if (str_starts_with($relative, "/")) {
            return $relative;
        }

        $index = strrpos($base, "/");
        if ($index === false) {
            return "/" . $relative;
        }

        return substr($base, 0, $index + 1) . $relative;
    }

    /**
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-5.2.4
     * @param string $path
     * @return string
     */
    protected function removeDotSegments(string $path): string
    {
        $resultParts = [];
        $parts = explode("/", $path);

        foreach ($parts as $part) {
            if ($part === "..") {
                array_pop($resultParts);
            } elseif ($part !== ".") {
                $resultParts[] = $part;
            }
        }

        $result = implode("/", $resultParts);

        if (str_starts_with($path, "/") && !str_starts_with($result, "/")) {
            $result = "/" . $result;
        } else if ($result !== "" && in_array(end($parts), [".", ".."])) {
            $result .= "/";
        }

        return $result;
    }
}
