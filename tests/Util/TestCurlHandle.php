<?php

namespace Tests\Util;

use Aternos\CurlPsr\Curl\CurlHandleInterface;
use Closure;
use Psr\Http\Message\StreamInterface;
use Throwable;

class TestCurlHandle implements CurlHandleInterface
{
    const array INFO_KEYS = [
        CURLINFO_RESPONSE_CODE => 'http_code',
        CURLINFO_HTTP_VERSION => 'http_version',
    ];

    protected array $options = [];
    protected int $errno = CURLE_OK;
    protected string $error = '';
    protected array $info = [
        "http_code" => 200,
        "http_version" => "1.1",
    ];
    protected array $responseHeaders = [];
    protected int $responseChunkSize = 8192;
    protected ?StreamInterface $responseBody = null;
    protected ?StreamInterface $requestBodySink = null;
    protected ?array $parsedRequestHeaders = null;
    protected ?array $parsedResponseHeaders = null;
    protected ?string $responseStatusLine = null;
    protected ?Closure $onBeforeRead = null;
    protected ?Closure $onAfterRead = null;
    protected ?Closure $onBeforeWrite = null;
    protected ?Closure $onAfterWrite = null;
    protected ?Throwable $execError = null;

    /**
     * @inheritDoc
     */
    public function setopt(int $option, mixed $value): bool
    {
        $this->options[$option] = $value;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function exec(): string|bool
    {
        try {
            return $this->doExec();
        } catch (Throwable $e) {
            $this->execError = $e;
            throw $e;
        }
    }

    /**
     * @return string|bool
     */
    public function doExec(): string|bool
    {
        $this->info['url'] = $this->options[CURLOPT_URL] ?? '';

        // Simulate request body upload
        if (isset($this->options[CURLOPT_READFUNCTION])) {
            $uploaded = 0;
            do {
                $this->onBeforeRead?->call($this);
                $chunk = $this->options[CURLOPT_READFUNCTION](null, $this->options[CURLOPT_INFILE] ?? null, 8192);
                $this->requestBodySink?->write($chunk);
                $uploaded += strlen($chunk);
                $this->onAfterRead?->call($this, $chunk);
                $this->progressUpdate(0, 0, $this->getRequestHeader("Content-Length") ?? 0, $uploaded);
            } while (strlen($chunk) > 0);
        }

        // Simulate response headers
        if ($this->responseStatusLine !== null) {
            array_unshift($this->responseHeaders, $this->responseStatusLine);
        } else {
            array_unshift($this->responseHeaders, "HTTP/" . $this->info["http_version"] . " " . $this->info['http_code'] . " TEST");
        }
        if (isset($this->options[CURLOPT_HEADERFUNCTION])) {
            foreach ($this->responseHeaders as $header) {
                $this->options[CURLOPT_HEADERFUNCTION](null, $header . "\r\n");
            }
        }

        // Simulate response body download
        if (isset($this->options[CURLOPT_WRITEFUNCTION]) && $this->responseBody !== null) {
            while (!$this->responseBody->eof()) {
                $this->onBeforeWrite?->call($this);
                $this->options[CURLOPT_WRITEFUNCTION](null, $this->responseBody->read($this->responseChunkSize));
                $this->progressUpdate(
                    $this->responseBody->getSize() ?? $this->getResponseHeader("Content-Length") ?? 0,
                    $this->responseBody->tell(), 0, 0);
                $this->onAfterWrite?->call($this);
            }
        }

        return true;
    }

    /**
     * @param int $downloadTotal
     * @param int $downloaded
     * @param int $uploadTotal
     * @param int $uploaded
     * @return void
     */
    protected function progressUpdate(int $downloadTotal, int $downloaded, int $uploadTotal, int $uploaded): void
    {
        $callback = $this->options[CURLOPT_XFERINFOFUNCTION] ?? $this->options[CURLOPT_PROGRESSFUNCTION] ?? null;
        if ($callback !== null) {
            $callback(null, $downloadTotal, $downloaded, $uploadTotal, $uploaded);
        }
    }

    /**
     * @inheritDoc
     */
    public function errno(): int
    {
        return $this->errno;
    }

    /**
     * @inheritDoc
     */
    public function error(): string
    {
        return $this->error;
    }

    /**
     * @inheritDoc
     */
    public function getinfo(?int $option = null): mixed
    {
        if ($option === null) {
            return $this->info;
        }
        $key = static::INFO_KEYS[$option] ?? null;
        if ($key !== null) {
            return $this->info[$key] ?? null;
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function close(): void
    {
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->options = [];
        $this->errno = 0;
        $this->error = '';
        $this->info = [];
    }

    /**
     * Merge options with existing options
     *
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options): static
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param int $errno
     * @return $this
     */
    public function setErrno(int $errno): static
    {
        $this->errno = $errno;
        return $this;
    }

    /**
     * @param string $error
     * @return $this
     */
    public function setError(string $error): static
    {
        $this->error = $error;
        return $this;
    }

    /**
     * Merge info with existing info
     *
     * @param array $info
     * @return $this
     */
    public function setInfo(array $info): static
    {
        $this->info = array_merge($this->info, $info);
        return $this;
    }

    /**
     * Merge response headers with existing response headers
     *
     * @param array $responseHeaders
     * @return $this
     */
    public function setResponseHeaders(array $responseHeaders): static
    {
        $this->responseHeaders = array_merge($this->responseHeaders, $responseHeaders);
        return $this;
    }

    /**
     * Set chunk size for response write function
     *
     * @param int $responseChunkSize
     * @return $this
     */
    public function setResponseChunkSize(int $responseChunkSize): static
    {
        $this->responseChunkSize = $responseChunkSize;
        return $this;
    }

    /**
     * Get the request body received from the read function
     *
     * @return StreamInterface
     */
    public function getRequestBodySink(): StreamInterface
    {
        return $this->requestBodySink;
    }

    /**
     * Set the stream to write the request body to
     * Request body is discarded if not set
     *
     * @param StreamInterface|null $requestBodySink
     * @return $this
     */
    public function setRequestBodySink(?StreamInterface $requestBodySink): static
    {
        $this->requestBodySink = $requestBodySink;
        return $this;
    }


    /**
     * Set the stream the response body is read from
     * No response body is written if not set
     *
     * @param StreamInterface|null $responseBody
     * @return $this
     */
    public function setResponseBody(?StreamInterface $responseBody): TestCurlHandle
    {
        $this->responseBody = $responseBody;
        return $this;
    }

    /**
     * @return StreamInterface|null
     */
    public function getResponseBody(): ?StreamInterface
    {
        return $this->responseBody;
    }

    /**
     * @param array $rawHeaders
     * @return array
     */
    protected function parseHeaders(array $rawHeaders): array
    {
        $parsed = [];
        foreach ($rawHeaders as $header) {
            $parts = array_map(trim(...), explode(": ", $header, 2));
            if (count($parts) === 1 && str_ends_with($parts[0], ";")) {
                $parts[0] = substr($parts[0], 0, -1);
                $parts[1] = "";
            }
            [$name, $value] = $parts;

            $name = strtolower($name);
            if (!isset($parsed[$name])) {
                $parsed[$name] = [];
            }
            $parsed[$name][] = $value;
        }
        return $parsed;
    }

    protected function parseRequestHeaders(): static
    {
        if ($this->parsedRequestHeaders !== null) {
            return $this;
        }
        $this->parsedRequestHeaders = $this->parseHeaders($this->options[CURLOPT_HTTPHEADER] ?? []);
        return $this;
    }

    protected function parseResponseHeaders(): static
    {
        if ($this->parsedResponseHeaders !== null) {
            return $this;
        }
        $this->parsedResponseHeaders = $this->parseHeaders($this->responseHeaders ?? []);
        return $this;
    }

    /**
     * @param string $name
     * @return string|null
     */
    public function getRequestHeader(string $name): ?string
    {
        $this->parseRequestHeaders();

        $name = strtolower($name);
        if (!isset($this->parsedRequestHeaders[$name])) {
            return null;
        }

        return $this->parsedRequestHeaders[$name][0];
    }

    /**
     * @param string $name
     * @return string|null
     */
    public function getResponseHeader(string $name): ?string
    {
        $this->parseResponseHeaders();

        $name = strtolower($name);
        if (!isset($this->parsedResponseHeaders[$name])) {
            return null;
        }

        return $this->parsedResponseHeaders[$name][0];
    }

    /**
     * Set a custom response status line
     * e.g. "HTTP/1.1 200 OK"
     *
     * Generated automatically if not set
     *
     * @param string|null $responseStatusLine
     * @return $this
     */
    public function setResponseStatusLine(?string $responseStatusLine): static
    {
        $this->responseStatusLine = $responseStatusLine;
        return $this;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function getOption($key): mixed
    {
        return $this->options[$key] ?? null;
    }

    /**
     * Called before the read function is called
     *
     * @param Closure|null $onBeforeRead
     * @return $this
     */
    public function setOnBeforeRead(?Closure $onBeforeRead): static
    {
        $this->onBeforeRead = $onBeforeRead;
        return $this;
    }

    /**
     * Called after the read function is called
     *
     * @param Closure|null $onAfterRead
     * @return $this
     */
    public function setOnAfterRead(?Closure $onAfterRead): static
    {
        $this->onAfterRead = $onAfterRead;
        return $this;
    }

    /**
     * Called before the write function is called
     *
     * @param Closure|null $onBeforeWrite
     * @return $this
     */
    public function setOnBeforeWrite(?Closure $onBeforeWrite): static
    {
        $this->onBeforeWrite = $onBeforeWrite;
        return $this;
    }

    /**
     * Called after the write function is called
     *
     * @param Closure|null $onAfterWrite
     * @return $this
     */
    public function setOnAfterWrite(?Closure $onAfterWrite): static
    {
        $this->onAfterWrite = $onAfterWrite;
        return $this;
    }

    /**
     * The error throws during the last call to exec
     *
     * @return Throwable|null
     */
    public function getExecError(): ?Throwable
    {
        return $this->execError;
    }

}
