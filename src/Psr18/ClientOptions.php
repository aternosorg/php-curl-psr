<?php

namespace Aternos\CurlPsr\Psr18;

use Closure;

class ClientOptions
{
    public int $timeout = 0;
    public int $maxRedirects = 10;
    public string $cookieFile = "";
    public ?Closure $progressCallback = null;
    public array $curlOptions = [];
    public array $defaultHeaders = [];
    public array $redirectToGetStatusCodes = [303];
}
