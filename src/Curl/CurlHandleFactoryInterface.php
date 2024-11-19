<?php

namespace Aternos\CurlPsr\Curl;

interface CurlHandleFactoryInterface
{
    /**
     * @return CurlHandleInterface
     */
    public function createCurlHandle(): CurlHandleInterface;
}
