<?php

namespace Aternos\CurlPsr\Psr18;

use Aternos\CurlPsr\Curl\CurlHandleFactory;
use Aternos\CurlPsr\Curl\CurlHandleFactoryInterface;
use Aternos\CurlPsr\Curl\CurlHandleInterface;
use Aternos\CurlPsr\Exception\NetworkException;
use Aternos\CurlPsr\Exception\RequestException;
use Aternos\CurlPsr\Exception\RequestRedirectedException;
use Aternos\CurlPsr\Exception\TooManyRedirectsException;
use Aternos\CurlPsr\Psr17\Psr17Factory;
use Aternos\CurlPsr\Psr18\UriResolver\UriResolver;
use Aternos\CurlPsr\Psr18\UriResolver\UriResolverInterface;
use Aternos\CurlPsr\Psr7\Stream\EmptyStream;
use Closure;
use Fiber;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriFactoryInterface;
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
    protected UriFactoryInterface $uriFactory;
    protected UriResolverInterface $uriResolver;
    protected CurlHandleFactoryInterface $curlHandleFactory;
    protected ClientOptions $options;

    /**
     * @param ResponseFactoryInterface|null $responseFactory
     * @param UriFactoryInterface|null $uriFactory
     * @param UriResolverInterface|null $uriResolver
     */
    public function __construct(
        ?ResponseFactoryInterface $responseFactory = null,
        ?UriFactoryInterface $uriFactory = null,
        ?UriResolverInterface $uriResolver = null
    )
    {
        $factory = new Psr17Factory();
        $this->responseFactory = $responseFactory ?? $factory;
        $this->uriFactory = $uriFactory ?? $factory;
        $this->uriResolver = $uriResolver ?? new UriResolver($this->uriFactory);
        $this->curlHandleFactory = new CurlHandleFactory();
        $this->options = new ClientOptions();
    }

    /**
     * @param CurlHandleFactoryInterface $curlHandleFactory
     * @return $this
     * @internal Used for testing
     */
    public function setCurlHandleFactory(CurlHandleFactoryInterface $curlHandleFactory): static
    {
        $this->curlHandleFactory = $curlHandleFactory;
        return $this;
    }

    /**
     * @param RequestInterface $request
     * @param ResponseHeaderParser $headerParser
     * @param ClientOptions $options
     * @return CurlHandleInterface
     */
    protected function initRequest(RequestInterface $request, ResponseHeaderParser $headerParser, ClientOptions $options): CurlHandleInterface
    {
        $ch = $this->curlHandleFactory->createCurlHandle();
        foreach ($options->curlOptions as $option => $value) {
            $ch->setopt($option, $value);
        }

        $ch->setopt(CURLOPT_URL, $request->getUri());
        $ch->setopt(CURLOPT_REQUEST_TARGET, $request->getRequestTarget());
        $ch->setopt(CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        $ch->setopt(CURLOPT_FOLLOWLOCATION, false);
        $ch->setopt(CURLOPT_COOKIEFILE, $options->cookieFile);
        $ch->setopt(CURLOPT_TIMEOUT, $options->timeout);
        $ch->setopt(CURLOPT_ACCEPT_ENCODING, "");

        if ($request->getProtocolVersion() === "1.0") {
            $ch->setopt(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        } else if ($request->getProtocolVersion() === "1.1") {
            $ch->setopt(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        } else if ($request->getProtocolVersion() === "2.0") {
            $ch->setopt(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
        }

        $requestBody = $request->getBody();
        $requestBodySize = $requestBody->getSize();
        if ($requestBodySize !== null && $requestBodySize >= 0) {
            $ch->setopt(CURLOPT_INFILESIZE, $requestBodySize);
            $request = $request->withHeader("Content-Length", $requestBodySize);
        }

        $ch->setopt(CURLOPT_UPLOAD, true);
        $ch->setopt(CURLOPT_READFUNCTION, function ($ch, $fd, $length) use ($requestBody) {
            do {
                $chunk = $requestBody->read($length);
            } while ($chunk === "" && $requestBody->eof() === false);
            return $chunk;
        });

        $ch->setopt(CURLOPT_WRITEFUNCTION, /** @throws Throwable */ function ($ch, $data) {
            Fiber::suspend($data);
            return strlen($data);
        });

        $progressCallback = $this->createProgressCallback($request, $options);
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
     * @param ClientOptions $options
     * @return Closure|null
     */
    protected function createProgressCallback(RequestInterface $request, ClientOptions $options): ?Closure
    {
        $callback = $options->progressCallback;
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
        return $this->doSendRequest($request, clone $this->options);
    }

    /**
     * Actually send the request
     *
     * @param RequestInterface $request
     * @param ClientOptions $options
     * @param int $redirects
     * @return ResponseInterface
     * @throws NetworkException
     * @throws RequestException
     */
    protected function doSendRequest(RequestInterface $request, ClientOptions $options, int $redirects = 0): ResponseInterface
    {
        foreach ($options->defaultHeaders as $name => $values) {
            if (!$request->hasHeader($name)) {
                $request = $request->withHeader($name, $values);
            }
        }

        $headerParser = new ResponseHeaderParser();
        $ch = $this->initRequest($request, $headerParser, $options);

        $fiber = new Fiber(function () use ($ch) {
            try {
                $ch->exec();
            } catch (RequestRedirectedException) {
                // ignore
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

        if ($options->followRedirects && $this->isRedirect($response)) {
            if (!$fiber->isTerminated()) {
                try {
                    $fiber->throw(new RequestRedirectedException());
                } catch (Throwable $e) {
                    throw new RequestException($request, "Could not close request before redirect", previous: $e);
                }
            }
            return $this->handleRedirect($request, $response, $options, $redirects);
        }

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
     * @param ResponseInterface $response
     * @return bool
     */
    protected function isRedirect(ResponseInterface $response): bool
    {
        if ($response->getStatusCode() > 300 && $response->getStatusCode() < 400) {
            return true;
        }

        return $response->getStatusCode() === 300 && $response->hasHeader("Location");
    }

    /**
     * Handle a redirect response
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param ClientOptions $options
     * @param int $redirects
     * @return ResponseInterface
     * @throws NetworkException
     * @throws RequestException
     * @throws TooManyRedirectsException
     */
    protected function handleRedirect(RequestInterface $request, ResponseInterface $response, ClientOptions $options, int $redirects): ResponseInterface
    {
        if ($redirects >= $options->maxRedirects) {
            throw new TooManyRedirectsException($request, "Redirect limit of " . $options->maxRedirects . " reached");
        }

        $locationHeaders = $response->getHeader("Location");
        if (count($locationHeaders) === 0) {
            throw new RequestException($request, "Redirect without location header");
        }
        if (count($locationHeaders) > 1) {
            throw new RequestException($request, "Multiple location headers in redirect");
        }

        try {
            $relativeUri = $this->uriFactory->createUri($locationHeaders[0]);
        } catch (Throwable $e) {
            throw new RequestException($request, "Invalid location header in redirect", previous: $e);
        }

        $location = $this->uriResolver->resolve($request->getUri(), $relativeUri);
        $request = $request->withUri($location);

        if (in_array($response->getStatusCode(), $options->redirectToGetStatusCodes)) {
            $request = $request->withMethod("GET")
                ->withBody(new EmptyStream())
                ->withoutHeader("Content-Length");
            return $this->doSendRequest($request, $options, $redirects + 1);
        }

        try {
            $this->rewindBody($request);
        } catch (Throwable $e) {
            throw new RequestException($request, "Could not rewind body for redirect", previous: $e);
        }

        return $this->doSendRequest($request, $options, $redirects + 1);
    }

    /**
     * @param RequestInterface $request
     * @return void
     * @throws RequestException
     */
    protected function rewindBody(RequestInterface $request): void
    {
        $body = $request->getBody();
        $offset = $body->tell();
        if ($offset === 0) {
            return;
        }

        if (!$body->isSeekable()) {
            throw new RequestException($request, "Request body is not seekable");
        }
        $body->rewind();
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
        $this->options->timeout = $timeout;
        return $this;
    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->options->timeout;
    }

    /**
     * Sets CURLOPT_MAXREDIRS and CURLOPT_FOLLOWLOCATION
     *
     * @param int $maxRedirects
     * @return $this
     */
    public function setMaxRedirects(int $maxRedirects): static
    {
        $this->options->maxRedirects = $maxRedirects;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxRedirects(): int
    {
        return $this->options->maxRedirects;
    }

    /**
     * Sets CURLOPT_COOKIEFILE
     *
     * @param string $cookieFile
     * @return $this
     */
    public function setCookieFile(string $cookieFile): static
    {
        $this->options->cookieFile = $cookieFile;
        return $this;
    }

    /**
     * @return string
     */
    public function getCookieFile(): string
    {
        return $this->options->cookieFile;
    }

    /**
     * A function compatible with CURLOPT_PROGRESSFUNCTION/CURLOPT_XFERINFOFUNCTION
     *
     * @param Closure|null $progressCallback
     * @return $this
     */
    public function setProgressCallback(?Closure $progressCallback): static
    {
        $this->options->progressCallback = $progressCallback;
        return $this;
    }

    /**
     * @return Closure|null
     */
    public function getProgressCallback(): ?Closure
    {
        return $this->options->progressCallback;
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
        $this->options->curlOptions[$option] = $value;
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
        return $this->options->curlOptions[$option] ?? null;
    }

    /**
     * @return array
     */
    public function getCurlOptions(): array
    {
        return $this->options->curlOptions;
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
        $this->options->defaultHeaders = $headers;
        return $this;
    }

    /**
     * @param string $name
     * @param string ...$values
     * @return $this
     */
    public function addDefaultHeader(string $name, string ...$values): static
    {
        foreach ($this->options->defaultHeaders as $headerName => $headerValues) {
            if (strtolower($headerName) === strtolower($name)) {
                $this->options->defaultHeaders[$headerName] = $values;
                return $this;
            }
        }
        $this->options->defaultHeaders[$name] = $values;
        return $this;
    }

    /**
     * @return string[][]
     */
    public function getDefaultHeaders(): array
    {
        return $this->options->defaultHeaders;
    }

    /**
     * Set a list of status codes that should be redirected to using GET.
     * By default, only 303 responses are redirected to using GET,
     * but historically 301 and 302 have also used this behavior.
     *
     * @param int[] $redirectToGetStatusCodes
     * @return $this
     */
    public function setRedirectToGetStatusCodes(array $redirectToGetStatusCodes): static
    {
        $this->options->redirectToGetStatusCodes = $redirectToGetStatusCodes;
        return $this;
    }

    /**
     * @return int[]
     */
    public function getRedirectToGetStatusCodes(): array
    {
        return $this->options->redirectToGetStatusCodes;
    }

    /**
     * @param bool $followRedirects
     * @return $this
     */
    public function setFollowRedirects(bool $followRedirects): static
    {
        $this->options->followRedirects = $followRedirects;
        return $this;
    }

    /**
     * @return bool
     */
    public function getFollowRedirects(): bool
    {
        return $this->options->followRedirects;
    }
}
