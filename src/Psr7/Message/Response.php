<?php

namespace Aternos\CurlPsr\Psr7\Message;

use Psr\Http\Message\ResponseInterface;

class Response extends Message implements ResponseInterface
{
    /**
     * @param int $statusCode
     * @param string $reasonPhrase
     */
    public function __construct(
        protected int $statusCode,
        protected string $reasonPhrase = "",
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @inheritDoc
     */
    public function withStatus(int $code, string $reasonPhrase = ""): static
    {
        if ($this->statusCode === $code && $this->reasonPhrase === $reasonPhrase) {
            return $this;
        }

        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase;
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }
}
