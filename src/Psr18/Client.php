<?php

namespace Aternos\CurlPsr\Psr18;

use Aternos\CurlPsr\Curl\CurlHandleFactory;
use Aternos\CurlPsr\Curl\CurlHandleFactoryInterface;
use Aternos\CurlPsr\Curl\CurlHandleInterface;
use Aternos\CurlPsr\Exception\NetworkException;
use Aternos\CurlPsr\Exception\RequestException;
use Aternos\CurlPsr\Psr17\Psr17Factory;
use Closure;
use Fiber;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class Client implements ClientInterface
{
    const array CONNECTION_ERRORS = [
        CURLE_COULDNT_CONNECT,
        CURLE_COULDNT_RESOLVE_HOST,
        CURLE_COULDNT_RESOLVE_PROXY,
        CURLE_GOT_NOTHING,
        CURLE_OPERATION_TIMEDOUT,
        CURLE_OPERATION_TIMEOUTED,
        CURLE_RECV_ERROR,
        CURLE_SEND_ERROR,
    ];

    protected ResponseFactoryInterface $responseFactory;
    protected CurlHandleFactoryInterface $curlHandleFactory;
    protected int $timeout = 0;
    protected int $maxRedirects = 10;
    protected string $cookieFile = "";
    protected ?Closure $progressCallback = null;
    protected array $customCurlOptions = [];
    protected array $defaultHeaders = [];

    /**
     * @param ResponseFactoryInterface|null $responseFactory
     * @param CurlHandleFactoryInterface|null $curlHandleFactory
     */
    public function __construct(
        ?ResponseFactoryInterface $responseFactory = null,
        ?CurlHandleFactoryInterface        $curlHandleFactory = null
    )
    {
        $this->responseFactory = $responseFactory ?? new Psr17Factory();
        $this->curlHandleFactory = $curlHandleFactory ?? new CurlHandleFactory();
    }

    /**
     * @param RequestInterface $request
     * @param ResponseHeaderParser $headerParser
     * @return CurlHandleInterface
     */
    protected function initRequest(RequestInterface $request, ResponseHeaderParser $headerParser): CurlHandleInterface
    {
        $ch = $this->curlHandleFactory->createCurlHandle();
        foreach ($this->customCurlOptions as $option => $value) {
            $ch->setopt($option, $value);
        }

        $ch->setopt(CURLOPT_URL, $request->getUri());
        $ch->setopt(CURLOPT_REQUEST_TARGET, $request->getRequestTarget());
        $ch->setopt(CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        $ch->setopt(CURLOPT_FOLLOWLOCATION, $this->maxRedirects > 0);
        $ch->setopt(CURLOPT_MAXREDIRS, $this->maxRedirects);
        $ch->setopt(CURLOPT_COOKIEFILE, $this->cookieFile);
        $ch->setopt(CURLOPT_TIMEOUT, $this->timeout);
        $ch->setopt(CURLOPT_ACCEPT_ENCODING, "");

        if ($request->getProtocolVersion() === "1.0") {
            $ch->setopt(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        }

        $requestBody = $request->getBody();
        $requestBodySize = $requestBody->getSize();
        if ($requestBodySize !== null && $requestBodySize >= 0) {
            $ch->setopt(CURLOPT_INFILESIZE, $requestBodySize);
            $request = $request->withHeader("Content-Length", $requestBodySize);
        }

        $ch->setopt(CURLOPT_UPLOAD, true);
        $ch->setopt(CURLOPT_READFUNCTION, function ($ch, $fd, $length) use ($requestBody) {
            return $requestBody->read($length);
        });

        $ch->setopt(CURLOPT_WRITEFUNCTION, /** @throws Throwable */ function ($ch, $data) {
            Fiber::suspend($data);
            return strlen($data);
        });

        $progressCallback = $this->createProgressCallback($request);
        if ($progressCallback !== null) {
            $ch->setopt(CURLOPT_NOPROGRESS, false);
            if ($this->shouldUseXferInfoFunction()) {
                $ch->setopt(CURLOPT_XFERINFOFUNCTION, $progressCallback);
            } else {
                $ch->setopt(CURLOPT_PROGRESSFUNCTION, $progressCallback);
            }
        }

        $ch->setopt(CURLOPT_HEADERFUNCTION, $headerParser->getClosure());
        $ch->setopt(CURLOPT_CUSTOMREQUEST, $request->getMethod());

        $this->setHeaders($request, $ch);

        return $ch;
    }

    /**
     * @return bool
     */
    protected function shouldUseXferInfoFunction(): bool
    {
        return defined("CURLOPT_XFERINFOFUNCTION");
    }

    /**
     * Wrap the progress callback before passing it to cURL
     * as to not expose the cURL handle to the user
     *
     * @param RequestInterface $request
     * @return Closure|null
     */
    protected function createProgressCallback(RequestInterface $request): ?Closure
    {
        $callback = $this->getProgressCallback();
        if ($callback === null) {
            return null;
        }
        return fn ($_, $downloadTotal, $downloadNow, $uploadTotal, $uploadNow) => $callback($request, $downloadTotal, $downloadNow, $uploadTotal, $uploadNow);
    }

    /**
     * @inheritDoc
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        foreach ($this->defaultHeaders as $name => $values) {
            if (!$request->hasHeader($name)) {
                $request = $request->withHeader($name, $values);
            }
        }

        $headerParser = new ResponseHeaderParser();
        $ch = $this->initRequest($request, $headerParser);

        $fiber = new Fiber(function () use ($ch) {
            try {
                $ch->exec();
            } catch (Throwable $e) {
                $ch->close();
                throw $e;
            }
            $ch->close();
        });

        try {
            $initial = $fiber->start();
        } catch (Throwable $e) {
            $ch->close();
            throw new RequestException($request, $e->getMessage(), $e->getCode(), $e);
        }

        $this->throwCurlError($ch, $request);

        $status = $ch->getinfo(CURLINFO_RESPONSE_CODE);
        $protocolVersion = $ch->getinfo(CURLINFO_HTTP_VERSION);
        $reasonPhrase = $headerParser->getReason();
        if ($reasonPhrase === null) {
            throw new RequestException($request, "Invalid response");
        }

        $response = $this->responseFactory
            ->createResponse($status, $reasonPhrase)
            ->withProtocolVersion($protocolVersion);

        $response = $headerParser->applyToResponse($response);

        $size = null;
        if ($response->hasHeader("Content-Encoding")) {
            $response = $response
                ->withoutHeader("Content-Encoding")
                ->withoutHeader("Content-Length");
        } else {
            $contentLengthHeader = $response->getHeader("Content-Length");
            if (count($contentLengthHeader) > 0) {
                $size = (int)$contentLengthHeader[0];
            }
        }

        return $response->withBody(new ResponseStream($ch, $fiber, $initial ?? "", $size));
    }

    /**
     * If there was an error, close the handle and throw an exception
     *
     * @param CurlHandleInterface $ch
     * @param RequestInterface $request
     * @return void
     * @throws NetworkException
     * @throws RequestException
     */
    protected function throwCurlError(CurlHandleInterface $ch, RequestInterface $request): void
    {
        $error = $ch->errno();
        if ($error === CURLE_OK) {
            return;
        }
        $message = $ch->error();
        $ch->close();
        if (in_array($error, static::CONNECTION_ERRORS)) {
            throw new NetworkException($request, $message, $error);
        } else {
            throw new RequestException($request, $message, $error);
        }
    }

    /**
     * @param RequestInterface $request
     * @param CurlHandleInterface $ch
     * @return void
     */
    protected function setHeaders(RequestInterface $request, CurlHandleInterface $ch): void
    {
        $headers = $request->getHeaders();
        $curlHeaders = [];
        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                if ($value === null || $value === "") {
                    $curlHeaders[] = $name . ";";
                } else {
                    $curlHeaders[] = $name . ": " . $value;
                }
            }
        }
        $ch->setopt(CURLOPT_HTTPHEADER, $curlHeaders);
    }

    /**
     * Sets CURLOPT_TIMEOUT
     *
     * @param int $timeout
     * @return $this
     */
    public function setTimeout(int $timeout): static
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Sets CURLOPT_MAXREDIRS and CURLOPT_FOLLOWLOCATION
     *
     * @param int $maxRedirects
     * @return $this
     */
    public function setMaxRedirects(int $maxRedirects): static
    {
        $this->maxRedirects = $maxRedirects;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxRedirects(): int
    {
        return $this->maxRedirects;
    }

    /**
     * Sets CURLOPT_COOKIEFILE
     *
     * @param string $cookieFile
     * @return $this
     */
    public function setCookieFile(string $cookieFile): static
    {
        $this->cookieFile = $cookieFile;
        return $this;
    }

    /**
     * @return string
     */
    public function getCookieFile(): string
    {
        return $this->cookieFile;
    }

    /**
     * A function compatible with CURLOPT_PROGRESSFUNCTION/CURLOPT_XFERINFOFUNCTION
     *
     * @param Closure|null $progressCallback
     * @return $this
     */
    public function setProgressCallback(?Closure $progressCallback): static
    {
        $this->progressCallback = $progressCallback;
        return $this;
    }

    /**
     * @return Closure|null
     */
    public function getProgressCallback(): ?Closure
    {
        return $this->progressCallback;
    }

    /**
     * Set a custom cURL option on all requests sent by this client
     *
     * @param int $option
     * @param mixed $value
     * @return $this
     */
    public function setCurlOption(int $option, mixed $value): static
    {
        $this->customCurlOptions[$option] = $value;
        return $this;
    }

    /**
     * Get a custom cURL option, or null if not set
     *
     * @param int $option
     * @return mixed
     */
    public function getCurlOption(int $option): mixed
    {
        return $this->customCurlOptions[$option] ?? null;
    }

    /**
     * Set default headers that will be sent with every request
     * in the format ["Header-Name" => ["Header Value 1", "Header Value 2", ...]]
     *
     * Note that a header will only be added if it is not already set on the request
     *
     * @param string[][] $headers
     * @return $this
     */
    public function setDefaultHeaders(array $headers): static
    {
        $this->defaultHeaders = $headers;
        return $this;
    }

    /**
     * @param string $name
     * @param string ...$values
     * @return $this
     */
    public function addDefaultHeader(string $name, string ...$values): static
    {
        foreach ($this->defaultHeaders as $headerName => $headerValues) {
            if (strtolower($headerName) === strtolower($name)) {
                $this->defaultHeaders[$headerName] = $values;
                return $this;
            }
        }
        $this->defaultHeaders[$name] = $values;
        return $this;
    }

    /**
     * @return string[][]
     */
    public function getDefaultHeaders(): array
    {
        return $this->defaultHeaders;
    }
}
