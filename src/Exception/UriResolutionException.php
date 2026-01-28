<?php

namespace Aternos\CurlPsr\Exception;

use Exception;
use Psr\Http\Message\UriInterface;
use Throwable;

class UriResolutionException extends Exception implements UriResolutionExceptionInterface
{
    /**
     * @param UriInterface $baseUri
     * @param UriInterface $targetUri
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(
        protected UriInterface $baseUri,
        protected UriInterface $targetUri,
        string                 $message = "",
        int                    $code = 0,
        ?Throwable             $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @inheritDoc
     */
    public function getBaseUri(): UriInterface
    {
        return $this->baseUri;
    }

    /**
     * @inheritDoc
     */
    public function getTargetUri(): UriInterface
    {
        return $this->targetUri;
    }
}
