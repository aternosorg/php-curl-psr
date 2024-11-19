<?php

namespace Aternos\CurlPsr\Curl;

interface CurlHandleInterface
{
    /**
     * @param int $option
     * @param mixed $value
     * @return bool
     */
    public function setopt(int $option, mixed $value): bool;

    /**
     * @return string|bool
     */
    public function exec(): string|bool;

    /**
     * @return int
     */
    public function errno(): int;

    /**
     * @return string
     */
    public function error(): string;

    /**
     * @param int|null $option
     * @return mixed
     */
    public function getinfo(?int $option = null): mixed;

    /**
     * @return void
     */
    public function close(): void;

    /**
     * @return void
     */
    public function reset(): void;
}
