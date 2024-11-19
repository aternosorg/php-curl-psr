<?php

namespace Tests;

use Aternos\CurlPsr\Psr7\Stream\StringStream;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class StringStreamTest extends TestCase
{
    public function testStringStream(): void
    {
        $stream = new StringStream("test");

        $this->assertEquals(4, $stream->getSize());
        $this->assertEquals(0, $stream->tell());
        $this->assertTrue($stream->isReadable());
        $this->assertTrue($stream->isWritable());
        $this->assertTrue($stream->isSeekable());
        $this->assertEquals("test", (string)$stream);

        $stream->seek(2);
        $this->assertEquals(2, $stream->tell());
        $this->assertEquals("st", $stream->read(2));
        $this->assertEquals(4, $stream->tell());

        $stream->rewind();
        $this->assertEquals(0, $stream->tell());
        $this->assertEquals("te", $stream->read(2));
        $this->assertEquals(2, $stream->tell());

        $stream->write("test");
        $stream->rewind();
        $this->assertEquals("tetest", $stream->getContents());
        $this->assertEquals(6, $stream->getSize());

        $stream->seek(2, SEEK_SET);
        $this->assertEquals(2, $stream->tell());
        $stream->seek(2, SEEK_CUR);
        $this->assertEquals(4, $stream->tell());
        $stream->seek(-1, SEEK_END);
        $this->assertEquals(5, $stream->tell());

        $this->assertEquals([
            'timed_out' => false,
            'blocked' => false,
            'eof' => false,
            'unread_bytes' => 1,
            'mode' => 'r+',
            'seekable' => true
        ], $stream->getMetadata());
        $this->assertFalse($stream->getMetadata("timed_out"));

        $stream->close(); // no effect
        $this->assertNull($stream->detach());
    }

    public function testNotReadable(): void
    {
        $stream = new StringStream("test", readable: false);

        $this->assertFalse($stream->isReadable());
        $this->assertEquals("w", $stream->getMetadata("mode"));
        $this->expectException(RuntimeException::class);
        $stream->read(2);
    }

    public function testNotReadableGetContents(): void
    {
        $stream = new StringStream("test", readable: false);

        $this->expectException(RuntimeException::class);
        $stream->getContents();
    }

    public function testNotReadableToString(): void
    {
        $stream = new StringStream("test", readable: false);

        $this->assertEquals("", (string)$stream);
    }

    public function testNotWritable(): void
    {
        $stream = new StringStream("test", writable: false);

        $this->assertFalse($stream->isWritable());
        $this->assertEquals("r", $stream->getMetadata("mode"));
        $this->expectException(RuntimeException::class);
        $stream->write("test");
    }

    public function testNotReadableAndWritable(): void
    {
        $stream = new StringStream("test", readable: false, writable: false);

        $this->assertFalse($stream->isReadable());
        $this->assertFalse($stream->isWritable());
        $this->assertNull($stream->getMetadata("mode"));
        $this->expectException(RuntimeException::class);
        $stream->read(2);
    }

    public function testNotSeekable(): void
    {
        $stream = new StringStream("test", seekable: false);

        $this->assertFalse($stream->isSeekable());
        $this->expectException(RuntimeException::class);
        $stream->seek(2);
    }

    public function testSeekInvalidWhence(): void
    {
        $stream = new StringStream("test");

        $this->expectException(RuntimeException::class);
        $stream->seek(2, 3);
    }
}
