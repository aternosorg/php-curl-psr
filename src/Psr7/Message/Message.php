<?php

namespace Aternos\CurlPsr\Psr7\Message;

use Aternos\CurlPsr\Psr7\Stream\StringStream;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

class Message implements MessageInterface
{
    /**
     * @var string[][]
     */
    protected array $headers = [];
    protected string $protocolVersion = "1.1";
    protected ?StreamInterface $body = null;

    /**
     * @inheritDoc
     */
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * @inheritDoc
     */
    public function withProtocolVersion(string $version): static
    {
        if ($this->protocolVersion === $version) {
            return $this;
        }

        $new = clone $this;
        $new->protocolVersion = $version;
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @inheritDoc
     */
    public function hasHeader(string $name): bool
    {
        foreach ($this->headers as $headerName => $headerValue) {
            if (strtolower($headerName) === strtolower($name)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getHeader(string $name): array
    {
        foreach ($this->headers as $headerName => $headerValues) {
            if (strtolower($headerName) === strtolower($name)) {
                return $headerValues;
            }
        }
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getHeaderLine(string $name): string
    {
        return implode(", ", $this->getHeader($name));
    }

    protected function getExistingHeaderName(string $name): ?string
    {
        foreach ($this->headers as $headerName => $headerValues) {
            if (strtolower($headerName) === strtolower($name)) {
                return $headerName;
            }
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function withHeader(string $name, $value): static
    {
        $new = clone $this;
        $existingHeaderName = $new->getExistingHeaderName($name);
        if ($existingHeaderName !== null) {
            unset($new->headers[$existingHeaderName]);
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        $new->headers[$name] = $value;
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function withAddedHeader(string $name, $value): static
    {
        $new = clone $this;
        $existingHeaderName = $new->getExistingHeaderName($name);

        if (!is_array($value)) {
            $value = [$value];
        }

        if ($existingHeaderName !== null) {
            $value = array_merge($new->headers[$existingHeaderName], $value);
        }
        $new->headers[$name] = $value;

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function withoutHeader(string $name): static
    {
        $existingHeaderName = $this->getExistingHeaderName($name);
        if ($existingHeaderName === null) {
            return $this;
        }

        $new = clone $this;
        unset($new->headers[$existingHeaderName]);
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getBody(): StreamInterface
    {
        if ($this->body === null) {
            $this->body = new StringStream("");
        }

        return $this->body;
    }

    /**
     * @inheritDoc
     */
    public function withBody(StreamInterface $body): static
    {
        if ($body === $this->body) {
            return $this;
        }

        $new = clone $this;
        $new->body = $body;
        return $new;
    }
}
