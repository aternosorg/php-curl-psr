<?php

namespace Aternos\CurlPsr\Exception;

use Psr\Http\Message\UriInterface;
use Throwable;

interface UriResolutionExceptionInterface extends Throwable
{
    /**
     * @return UriInterface
     */
    public function getBaseUri(): UriInterface;

    /**
     * @return UriInterface
     */
    public function getTargetUri(): UriInterface;
}
