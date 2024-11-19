<?php

namespace Tests;

use Aternos\CurlPsr\Curl\WrappedCurlHandle;
use PHPUnit\Framework\TestCase;

class CurlHandleTest extends TestCase
{
    public function testCurlHandle(): void
    {
        $ch = curl_init();
        $wrapped = new WrappedCurlHandle($ch);

        $this->assertTrue($wrapped->setopt(CURLOPT_URL, "https://example.com"));
        $this->assertSame("https://example.com", $wrapped->getinfo(CURLINFO_EFFECTIVE_URL));

        $this->assertSame(curl_errno($ch), $wrapped->errno());
        $this->assertSame(curl_error($ch), $wrapped->error());
        $this->assertSame(curl_getinfo($ch), $wrapped->getinfo());
        $this->assertSame(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL), $wrapped->getinfo(CURLINFO_EFFECTIVE_URL));

        $wrapped->reset();
        $this->assertSame("", $wrapped->getinfo(CURLINFO_EFFECTIVE_URL));

        $this->assertFalse($wrapped->exec());

        $wrapped->close();
    }
}
