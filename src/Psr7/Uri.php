<?php

namespace Aternos\CurlPsr\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;
use Uri\InvalidUriException;

/**
 * This class is a wrapper around the \Uri\Rfc3986\Uri class from ext-uri that implements the PSR-7 UriInterface.
 * While both a similar, there are some differences in behavior and encoding that need to be handled.
 *  - ext-uri only accepts already percent-encoded strings for its components, while PSR-7 expects implementations
 *   to detect if the string is already percent-encoded or not and encode it if necessary.
 * - ext-uri does not remove the default port for http (80) and https (443) when getting the port,
 *   while PSR-7 expects the default port to be removed.
 * - ext-uri requires a host to be set when setting user info, port or path. PSR-7 does not specify this behavior,
 *   but most implementations allow setting these components without a host.
 */
class Uri implements UriInterface
{
    const string SUB_DELIMITERS = "!\$&'\(\)\*\+,;=";
    const string ALPHA = "a-zA-Z";
    const string DIGIT = "0-9";
    const string UNRESERVED_CHARACTERS = self::ALPHA . self::DIGIT . "\-\._~";

    protected \Uri\Rfc3986\Uri $uri;

    /**
     * @param string|\Uri\Rfc3986\Uri $uri
     */
    public function __construct(
        string|\Uri\Rfc3986\Uri $uri = ""
    )
    {
        if (is_string($uri)) {
            $uri = \Uri\Rfc3986\Uri::parse($uri);
            if ($uri === null) {
                throw new InvalidArgumentException("Invalid URI: " . $uri);
            }
        }
        $this->uri = $uri;
    }

    /**
     * @param string $string
     * @param string $pattern
     * @return string
     */
    protected function encodeString(string $string, string $pattern) : string
    {
        $result = "";
        $length = strlen($string);
        for ($i = 0; $i < $length; $i++) {
            $char = $string[$i];
            if ($char === "%" && $i + 2 < $length) {
                $hex = substr($string, $i + 1, 2);
                if (preg_match("#[0-9a-fA-F]{2}#", $hex)) {
                    $result .= $char . $hex;
                    $i += 2;
                    continue;
                }
            }

            if (preg_match($pattern, $char)) {
                $result .= rawurlencode($char);
            } else {
                $result .= $char;
            }
        }
        return $result;
    }

    /**
     * @param \Uri\Rfc3986\Uri $uri
     * @return $this
     */
    protected function cloneWithUri(\Uri\Rfc3986\Uri $uri): static
    {
        $clone = clone $this;
        $clone->uri = $uri;
        return $clone;
        /*return clone($this, [
            "uri" => $uri
        ]);*/
    }

    /**
     * @inheritDoc
     */
    public function getScheme(): string
    {
        return $this->uri->getScheme() ?? "";
    }

    /**
     * @inheritDoc
     */
    public function getAuthority(): string
    {
        $userInfo = $this->getUserInfo();
        $host = $this->getHost();
        $port = $this->getPort();

        $authority = "";
        if ($userInfo !== "") {
            $authority .= $userInfo . "@";
        }
        $authority .= $host;
        if ($port !== null) {
            $authority .= ":" . $port;
        }

        return $authority;
    }

    /**
     * @inheritDoc
     */
    public function getUserInfo(): string
    {
        return $this->uri->getUserInfo() ?? "";
    }

    /**
     * @inheritDoc
     */
    public function getHost(): string
    {
        return $this->uri->getHost() ?? "";
    }

    /**
     * @inheritDoc
     */
    public function getPort(): ?int
    {
        $port = $this->uri->getPort();
        $scheme = $this->getScheme();
        if ($scheme === "http" && $port === 80) {
            return null;
        }
        if ($scheme === "https" && $port === 443) {
            return null;
        }
        return $port;
    }

    /**
     * @inheritDoc
     */
    public function getPath(): string
    {
        return $this->uri->getPath();
    }

    /**
     * @inheritDoc
     */
    public function getQuery(): string
    {
        return $this->uri->getQuery() ?? "";
    }

    /**
     * @inheritDoc
     */
    public function getFragment(): string
    {
        return $this->uri->getFragment() ?? "";
    }

    /**
     * @inheritDoc
     */
    public function withScheme(string $scheme): UriInterface
    {
        if ($scheme === "") {
            $scheme = null;
        }

        if ($this->uri->getRawScheme() === $scheme) {
            return $this;
        }

        try {
            return $this->cloneWithUri($this->uri->withScheme($scheme));
        } catch (InvalidUriException $e) {
            throw new InvalidArgumentException("Invalid scheme: " . $scheme, previous: $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function withUserInfo(string $user, ?string $password = null): UriInterface
    {
        $pattern = "#[^" . static::UNRESERVED_CHARACTERS . static::SUB_DELIMITERS . "]#";
        if ($user === "") {
            $userInfo = null;
        } else {
            $userInfo = $this->encodeString($user, $pattern);
            if ($password !== null) {
                $userInfo .= ":" . $this->encodeString($password, $pattern);
            }
        }

        if ($this->uri->getRawUserInfo() === $userInfo) {
            return $this;
        }

        $uri = $this->uri;
        if ($uri->getHost() === null && $userInfo !== null) {
            $uri = $uri->withHost("");
        }

        return $this->cloneWithUri($uri->withUserInfo($userInfo));
    }

    /**
     * @inheritDoc
     */
    public function withHost(string $host): UriInterface
    {
        if ($host === "") {
            $host = null;
        }

        if ($this->uri->getRawHost() === $host) {
            return $this;
        }

        try {
            return $this->cloneWithUri($this->uri->withHost($host));
        } catch (InvalidUriException $e) {
            throw new InvalidArgumentException("Invalid host: " . $host, previous: $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function withPort(?int $port): UriInterface
    {
        if ($this->uri->getPort() === $port) {
            return $this;
        }

        $uri = $this->uri;
        if ($uri->getHost() === null && $port !== null) {
            $uri = $uri->withHost("");
        }

        try {
            return $this->cloneWithUri($uri->withPort($port));
        } catch (InvalidUriException $e) {
            throw new InvalidArgumentException("Invalid port: " . $port, previous: $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function withPath(string $path): UriInterface
    {
        $path = $this->encodeString($path, "#[^" . static::UNRESERVED_CHARACTERS . static::SUB_DELIMITERS . ":@/" . "]#");
        if ($this->uri->getRawPath() === $path) {
            return $this;
        }

        if ($this->uri->getHost() !== null && $path !== "" && $path[0] !== "/") {
            $path = "/" . $path;
        }

        try {
            return $this->cloneWithUri($this->uri->withPath($path));
            // @codeCoverageIgnoreStart
        } catch (InvalidUriException $e) {
            throw new InvalidArgumentException("Invalid path: " . $path, previous: $e);
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @inheritDoc
     */
    public function withQuery(string $query): UriInterface
    {
        if ($query === "") {
            $query = null;
        } else {
            $query = $this->encodeString($query, "#[^" . static::UNRESERVED_CHARACTERS . static::SUB_DELIMITERS . ":@/?]" . "#");
        }

        if ($this->uri->getRawQuery() === $query) {
            return $this;
        }

        try {
            return $this->cloneWithUri($this->uri->withQuery($query));
            // @codeCoverageIgnoreStart
        } catch (InvalidUriException $e) {
            throw new InvalidArgumentException("Invalid query: " . $query, previous: $e);
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @inheritDoc
     */
    public function withFragment(string $fragment): UriInterface
    {
        if ($fragment === "") {
            $fragment = null;
        } else {
            $fragment = $this->encodeString($fragment, "#[^" . static::UNRESERVED_CHARACTERS . static::SUB_DELIMITERS . ":@/?]" . "#");
        }

        if ($this->uri->getRawFragment() === $fragment) {
            return $this;
        }

        try {
            return $this->cloneWithUri($this->uri->withFragment($fragment));
            // @codeCoverageIgnoreStart
        } catch (InvalidUriException $e) {
            throw new InvalidArgumentException("Invalid fragment: " . $fragment, previous: $e);
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $uri = $this->uri;
        if ($this->getPort() === null && $uri->getPort() !== null) {
            $uri = $uri->withPort(null);
        }

        return $uri->toRawString();
    }

    /**
     * @return \Uri\Rfc3986\Uri
     */
    public function getUri(): \Uri\Rfc3986\Uri
    {
        return $this->uri;
    }
}
