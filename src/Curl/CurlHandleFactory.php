<?php

namespace Aternos\CurlPsr\Curl;

class CurlHandleFactory implements CurlHandleFactoryInterface
{
    /**
     * @inheritDoc
     */
    public function createCurlHandle(): CurlHandleInterface
    {
        return new WrappedCurlHandle(curl_init());
    }
}
