<?php

namespace Aternos\CurlPsr\Exception;

use Exception;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Throwable;

class NetworkException extends Exception implements NetworkExceptionInterface
{
    /**
     * @param RequestInterface $request
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(
        protected RequestInterface $request,
        string                     $message = "",
        int                        $code = 0,
        ?Throwable                 $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @inheritDoc
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
