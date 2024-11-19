<?php

namespace Tests;

use Aternos\CurlPsr\Psr7\Message\Message;
use Aternos\CurlPsr\Psr7\Stream\StringStream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

class MessageTest extends TestCase
{
    protected Message $message;

    protected function setUp(): void
    {
        parent::setUp();
        $this->message = new Message();
    }

    public function testMessage(): void
    {
        $this->assertEquals("1.1", $this->message->getProtocolVersion());
        $this->assertEquals([], $this->message->getHeaders());
        $this->assertFalse($this->message->hasHeader("test"));

        $message = $this->message->withProtocolVersion("2.0");
        $this->assertSame($message, $message->withProtocolVersion("2.0"));
        $this->assertEquals("2.0", $message->getProtocolVersion());

        $message = $message->withHeader("test", "value");
        $this->assertEquals(["test" => ["value"]], $message->getHeaders());
        $message = $message->withHeader("test", "value2");
        $this->assertEquals(["test" => ["value2"]], $message->getHeaders());
        $message = $message->withAddedHeader("test", "value3");
        $this->assertEquals(["test" => ["value2", "value3"]], $message->getHeaders());
        $this->assertTrue($message->hasHeader("test"));
        $this->assertEquals("value2, value3", $message->getHeaderLine("test"));
        $this->assertEquals(["value2", "value3"], $message->getHeader("test"));
        $message = $message->withoutHeader("test");
        $this->assertEquals([], $message->getHeaders());
        $this->assertSame($message, $message->withoutHeader("test"));

        $body = $message->getBody();
        $this->assertInstanceOf(StreamInterface::class, $body);
        $this->assertEquals(0, $body->getSize());
        $this->assertEquals("", (string)$body);

        $message = $message->withBody(new StringStream("test"));
        $body = $message->getBody();
        $this->assertSame($message, $message->withBody($body));
        $this->assertEquals(4, $body->getSize());
        $this->assertEquals("test", (string)$body);
    }
}
