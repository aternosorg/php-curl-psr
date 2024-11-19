<?php

namespace Aternos\CurlPsr\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

class Uri implements UriInterface
{
    const string SUB_DELIMITERS = "!\$&'\(\)\*\+,;=";
    const string ALPHA = "a-zA-Z";
    const string DIGIT = "0-9";
    const string UNRESERVED_CHARACTERS = self::ALPHA . self::DIGIT . "\-\._~";

    protected string $scheme = "";
    protected string $user = "";
    protected ?string $password = "";
    protected string $host = "";
    protected ?int $port = null;
    protected string $path = "";
    protected string $query = "";
    protected string $fragment = "";

    /**
     * @param string|null $uri
     */
    public function __construct(?string $uri = null)
    {
        if ($uri !== null) {
            $this->parse($uri);
        }
    }

    /**
     * @param string $uri
     * @return $this
     */
    protected function parse(string $uri): static
    {
        $parsed = parse_url($uri);
        if ($parsed === false) {
            throw new InvalidArgumentException("Invalid URI " . $uri);
        }

        if (isset($parsed["scheme"])) {
            $this->setScheme($parsed["scheme"]);
        }
        if (isset($parsed["user"])) {
            $this->setUserInfo($parsed["user"], $parsed["pass"] ?? null);
        }
        if (isset($parsed["host"])) {
            $this->setHost($parsed["host"]);
        }
        if (isset($parsed["port"])) {
            $this->setPort($parsed["port"]);
        }
        if (isset($parsed["path"])) {
            $this->setPath($parsed["path"]);
        }
        if (isset($parsed["query"])) {
            $this->setQuery($parsed["query"]);
        }
        if (isset($parsed["fragment"])) {
            $this->setFragment($parsed["fragment"]);
        }
        return $this;
    }

    /**
     * @param string $string
     * @param string $pattern
     * @return string
     */
    protected function encode(string $string, string $pattern) : string
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
     * @param int|null $port
     * @return void
     */
    protected function setPort(?int $port): void
    {
        if ($port === null) {
            $this->port = null;
            return;
        }
        if ($port < 0 || $port > 65535) {
            throw new InvalidArgumentException("Invalid port " . $port);
        }
        $this->port = $port;
    }

    /**
     * @param string $scheme
     * @return void
     */
    protected function setScheme(string $scheme): void
    {
        $scheme = strtolower($scheme);
        if (!preg_match("#^[" . static::ALPHA . "][" . static::ALPHA . static::DIGIT . "+\-.]*$#", $scheme)) {
            throw new InvalidArgumentException("Invalid scheme " . $scheme);
        }
        $this->scheme = $scheme;
    }

    /**
     * @param string $user
     * @param string|null $password
     * @return void
     */
    protected function setUserInfo(string $user, ?string $password): void
    {
        $pattern = "#[" . static::UNRESERVED_CHARACTERS . static::SUB_DELIMITERS . "]#";
        if (strlen($user) === 0) {
            $this->user = "";
            $this->password = null;
            return;
        }

        $this->user = $this->encode($user, $pattern);
        if ($password === null && strlen($password) === 0) {
            $this->password = null;
            return;
        }

        $this->password = $this->encode($password, $pattern);
    }

    /**
     * @param string $host
     * @return void
     */
    protected function setHost(string $host): void
    {
        $host = strtolower($host);
        if (strlen($host) === 0) {
            $this->host = "";
            return;
        }

        if (str_starts_with($host, "[") && str_ends_with($host, "]")) {
            if (!filter_var(substr($host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                throw new InvalidArgumentException("Invalid host " . $host);
            }
            $this->host = $host;
            return;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->host = $host;
            return;
        }

        $this->host = $this->encode($host, "#[^" . static::UNRESERVED_CHARACTERS . static::SUB_DELIMITERS . "]#");
    }

    /**
     * @param string $path
     * @return void
     */
    protected function setPath(string $path): void
    {
        $this->path = $this->encode($path, "#[^" . static::UNRESERVED_CHARACTERS . static::SUB_DELIMITERS . ":@/" . "]#");
    }

    /**
     * @param string $query
     * @return void
     */
    protected function setQuery(string $query): void
    {
        $this->query = $this->encode($query, "#[^" . static::UNRESERVED_CHARACTERS . static::SUB_DELIMITERS . ":@/?]" . "#");
    }

    /**
     * @param string $fragment
     * @return void
     */
    protected function setFragment(string $fragment): void
    {
        $this->fragment = $this->encode($fragment, "#[^" . static::UNRESERVED_CHARACTERS . static::SUB_DELIMITERS . ":@/?]" . "#");
    }

    /**
     * @inheritDoc
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * @inheritDoc
     */
    public function getAuthority(): string
    {
        $userInfo = $this->getUserInfo();
        $host = $this->getHost();
        $port = $this->getPort();

        if ($host === "") {
            return "";
        }

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
        if ($this->user === "") {
            return "";
        }

        $userInfo = $this->user;
        if ($this->password !== null) {
            $userInfo .= ":" . $this->password;
        }
        return $userInfo;
    }

    /**
     * @inheritDoc
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @inheritDoc
     */
    public function getPort(): ?int
    {
        if ($this->scheme === "http" && $this->port === 80) {
            return null;
        }
        if ($this->scheme === "https" && $this->port === 443) {
            return null;
        }
        return $this->port;
    }

    /**
     * @inheritDoc
     */
    public function getPath(): string
    {
        $path = $this->path;
        $authority = $this->getAuthority();
        if ($path !== "") {
            if (!str_starts_with($path, "/")) {
                if ($authority !== "") {
                    return "/" . $path;
                }
            } else if ($authority === "") {
                return preg_replace("#^/+#", "/", $path);
            }
        }
        return $path;
    }

    /**
     * @inheritDoc
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @inheritDoc
     */
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * @inheritDoc
     */
    public function withScheme(string $scheme): UriInterface
    {
        if ($this->scheme === $scheme) {
            return $this;
        }

        $new = clone $this;
        $new->setScheme($scheme);
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function withUserInfo(string $user, ?string $password = null): UriInterface
    {
        if ($user === "" && $this->user === "") {
            return $this;
        }

        if (strlen($password) === 0) {
            $password = null;
        }

        $new = clone $this;
        $new->setUserInfo($user, $password);
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function withHost(string $host): UriInterface
    {
        if ($this->host === $host) {
            return $this;
        }

        $new = clone $this;
        $new->setHost($host);
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function withPort(?int $port): UriInterface
    {
        if ($port === $this->port) {
            return $this;
        }

        $new = clone $this;
        $new->setPort($port);
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function withPath(string $path): UriInterface
    {
        if ($this->path === $path) {
            return $this;
        }

        $new = clone $this;
        $new->setPath($path);
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function withQuery(string $query): UriInterface
    {
        if ($this->query === $query) {
            return $this;
        }

        $new = clone $this;
        $new->setQuery($query);
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function withFragment(string $fragment): UriInterface
    {
        if ($this->fragment === $fragment) {
            return $this;
        }

        $new = clone $this;
        $new->setFragment($fragment);
        return $new;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $path = $this->getPath();
        $authority = $this->getAuthority();
        $scheme = $this->getScheme();
        $query = $this->getQuery();
        $fragment = $this->getFragment();

        $uri = "";
        if ($scheme !== "") {
            $uri .= $scheme . ":";
        }
        if ($authority !== "") {
            $uri .= "//" . $authority;
        }
        $uri .= $path;
        if ($query !== "") {
            $uri .= "?" . $query;
        }
        if ($fragment !== "") {
            $uri .= "#" . $fragment;
        }

        return $uri;
    }
}
