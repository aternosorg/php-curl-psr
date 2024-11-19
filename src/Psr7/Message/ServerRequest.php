<?php

namespace Aternos\CurlPsr\Psr7\Message;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class ServerRequest extends Request implements ServerRequestInterface
{
    protected array $cookieParams = [];
    protected array $queryParams = [];
    protected array $uploadedFiles = [];
    protected mixed $parsedBody = null;
    protected array $attributes = [];

    /**
     * @param string $method
     * @param UriInterface $uri
     * @param array $serverParams
     */
    public function __construct(
        string          $method,
        UriInterface    $uri,
        protected array $serverParams = []
    )
    {
        parent::__construct($method, $uri);
    }

    /**
     * @inheritDoc
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * @inheritDoc
     */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * @inheritDoc
     */
    public function withCookieParams(array $cookies): static
    {
        $new = clone $this;
        $new->cookieParams = $cookies;
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * @inheritDoc
     */
    public function withQueryParams(array $query): static
    {
        $new = clone $this;
        $new->queryParams = $query;
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * @inheritDoc
     */
    public function withUploadedFiles(array $uploadedFiles): static
    {
        $new = clone $this;
        $new->uploadedFiles = $uploadedFiles;
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    /**
     * @inheritDoc
     */
    public function withParsedBody($data): static
    {
        $new = clone $this;
        $new->parsedBody = $data;
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @inheritDoc
     */
    public function getAttribute(string $name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function withAttribute(string $name, $value): static
    {
        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function withoutAttribute(string $name): static
    {
        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }
}
