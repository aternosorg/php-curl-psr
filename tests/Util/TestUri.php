<?php

namespace Tests\Util;

use Psr\Http\Message\UriInterface;
use Throwable;

class TestUri implements UriInterface
{
    /**
     * @param UriInterface $uri
     * @param array $hooks
     */
    public function __construct(
        protected UriInterface $uri,
        protected array $hooks = []
    )
    {
    }

    public function addHook(string $method, mixed $th): static
    {
        $this->hooks[$method] = $this->hooks[$method] ??= [];
        $this->hooks[$method][] = $th;
        return $this;
    }

    /**
     * @param string $method
     * @param array $args
     * @param mixed|null $fallback
     * @return mixed
     * @throws Throwable
     */
    protected function callHook(string $method, array $args = [], mixed $fallback = null): mixed
    {
        if (!isset($this->hooks[$method]) || count($this->hooks[$method]) === 0) {
            return $fallback;
        }

        $entry = array_shift($this->hooks[$method]);
        if ($entry instanceof Throwable) {
            throw $entry;
        }
        if (is_callable($entry)) {
            return call_user_func_array($entry, $args);
        }
        return $entry;
    }

    /**
     * @inheritDoc
     */
    public function getScheme(): string
    {
        return $this->callHook("getScheme", fallback: $this->uri->getScheme());
    }

    /**
     * @inheritDoc
     */
    public function getAuthority(): string
    {
        return $this->callHook("getAuthority", fallback: $this->uri->getAuthority());
    }

    /**
     * @inheritDoc
     */
    public function getUserInfo(): string
    {
        return $this->callHook("getUserInfo", fallback: $this->uri->getUserInfo());
    }

    /**
     * @inheritDoc
     */
    public function getHost(): string
    {
        return $this->callHook("getHost", fallback: $this->uri->getHost());
    }

    /**
     * @inheritDoc
     */
    public function getPort(): ?int
    {
        return $this->callHook("getPort", fallback: $this->uri->getPort());
    }

    /**
     * @inheritDoc
     */
    public function getPath(): string
    {
        return $this->callHook("getPath", fallback: $this->uri->getPath());
    }

    /**
     * @inheritDoc
     */
    public function getQuery(): string
    {
        return $this->callHook("getQuery", fallback: $this->uri->getQuery());
    }

    /**
     * @inheritDoc
     */
    public function getFragment(): string
    {
        return $this->callHook("getFragment", fallback: $this->uri->getFragment());
    }

    /**
     * @inheritDoc
     */
    public function withScheme(string $scheme): UriInterface
    {
        return new static($this->uri->withScheme($scheme), $this->hooks);
    }

    /**
     * @inheritDoc
     */
    public function withUserInfo(string $user, ?string $password = null): UriInterface
    {
        return new static($this->uri->withUserInfo($user, $password), $this->hooks);
    }

    /**
     * @inheritDoc
     */
    public function withHost(string $host): UriInterface
    {
        return new static($this->uri->withHost($host), $this->hooks);
    }

    /**
     * @inheritDoc
     */
    public function withPort(?int $port): UriInterface
    {
        return new static($this->uri->withPort($port), $this->hooks);
    }

    /**
     * @inheritDoc
     */
    public function withPath(string $path): UriInterface
    {
        return new static($this->uri->withPath($path), $this->hooks);
    }

    /**
     * @inheritDoc
     */
    public function withQuery(string $query): UriInterface
    {
        return new static($this->uri->withQuery($query), $this->hooks);
    }

    /**
     * @inheritDoc
     */
    public function withFragment(string $fragment): UriInterface
    {
        return new static($this->uri->withFragment($fragment), $this->hooks);
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->callHook("__toString", fallback: $this->uri->__toString());
    }
}
