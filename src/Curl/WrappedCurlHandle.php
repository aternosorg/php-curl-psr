<?php

namespace Aternos\CurlPsr\Curl;

use CurlHandle;

class WrappedCurlHandle implements CurlHandleInterface
{
    public function __construct(
        protected CurlHandle $ch
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function setopt(int $option, mixed $value): bool
    {
        return curl_setopt($this->ch, $option, $value);
    }

    /**
     * @inheritDoc
     */
    public function exec(): string|bool
    {
        return curl_exec($this->ch);
    }

    /**
     * @inheritDoc
     */
    public function errno(): int
    {
        return curl_errno($this->ch);
    }

    /**
     * @inheritDoc
     */
    public function error(): string
    {
        return curl_error($this->ch);
    }

    /**
     * @inheritDoc
     */
    public function getinfo(?int $option = null): mixed
    {
        return curl_getinfo($this->ch, $option);
    }

    /**
     * @inheritDoc
     */
    public function close(): void
    {
        curl_close($this->ch);
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        curl_reset($this->ch);
    }
}
