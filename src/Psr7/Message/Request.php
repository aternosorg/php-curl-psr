<?php

namespace Aternos\CurlPsr\Psr7\Message;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

class Request extends Message implements RequestInterface
{
    protected ?string $requestTarget = null;

    /**
     * @param string $method
     * @param UriInterface $uri
     */
    public function __construct(
        protected string $method,
        protected UriInterface $uri
    )
    {
        if (($host = $this->uri->getHost()) !== "") {
            $this->headers["Host"] = [$host];
        }
    }

    /**
     * @inheritDoc
     */
    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if ($target === "") {
            $target = "/";
        }

        if ($this->uri->getQuery()) {
            $target .= '?' . $this->uri->getQuery();
        }

        return $target;
    }

    /**
     * @inheritDoc
     */
    public function withRequestTarget(string $requestTarget): static
    {
        if ($this->requestTarget === $requestTarget) {
            return $this;
        }

        $new = clone $this;
        $new->requestTarget = $requestTarget;
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @inheritDoc
     */
    public function withMethod(string $method): static
    {
        if ($this->method === $method) {
            return $this;
        }

        $new = clone $this;
        $new->method = $method;
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * @inheritDoc
     */
    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        $new = clone $this;
        if ((!$preserveHost || $this->getHeaderLine("Host") === "") && $uri->getHost() !== "") {
            $new = $new->withHeader("Host", $uri->getHost());
        }
        $new->uri = $uri;
        return $new;
    }
}
