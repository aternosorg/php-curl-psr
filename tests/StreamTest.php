<?php

namespace Tests;

use Aternos\CurlPsr\Psr7\Stream\Stream;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use RuntimeException;

class StreamTest extends TestCase
{
    protected string $tmpPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpPath = tempnam(sys_get_temp_dir(), 'test');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (file_exists($this->tmpPath)) {
            unlink($this->tmpPath);
        }
    }

    public function testStream(): void
    {
        $stream = new Stream(fopen("php://memory", "r+"));
        $stream->write("test");
        $stream->rewind();

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
        $this->assertEquals("", $stream->read(0));
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
            'blocked' => true,
            'eof' => false,
            'unread_bytes' => 0,
            'mode' => 'w+b',
            'seekable' => true,
            'wrapper_type' => 'PHP',
            'stream_type' => 'MEMORY',
            'uri' => 'php://memory'
        ], $stream->getMetadata());
        $this->assertFalse($stream->getMetadata("timed_out"));

        $stream->close();
        $this->assertNull($stream->detach());
    }

    public function testNotReadable(): void
    {
        $stream = new Stream(fopen($this->tmpPath, "w"));

        $this->assertFalse($stream->isReadable());
        $this->assertStringStartsWith("w", $stream->getMetadata("mode"));
        $this->expectException(RuntimeException::class);
        $stream->read(2);
    }

    public function testNotReadableGetContents(): void
    {
        $stream = new Stream(fopen($this->tmpPath, "w"));

        $this->expectException(RuntimeException::class);
        $stream->getContents();
    }

    public function testNotReadableToString(): void
    {
        $stream = new Stream(fopen($this->tmpPath, "w"));

        $this->assertEquals("", (string)$stream);
    }

    public function testNotWritable(): void
    {
        $stream = new Stream(fopen($this->tmpPath, "r"));

        $this->assertFalse($stream->isWritable());
        $this->assertEquals("r", $stream->getMetadata("mode"));
        $this->expectException(RuntimeException::class);
        $stream->write("test");
    }

    public function testNotSeekable(): void
    {
        $stream = new Stream(fopen("php://output", "r"));

        $this->assertFalse($stream->isSeekable());
        $this->expectException(RuntimeException::class);
        $stream->seek(2);
    }

    public function testSeekInvalidWhence(): void
    {
        $stream = new Stream(fopen("php://memory", "r"));

        $this->expectException(RuntimeException::class);
        $stream->seek(2, 3);
    }

    public function testCloseDoesNothingOnDetachedResource(): void
    {
        $stream = new Stream(fopen("php://memory", "r+"));
        $stream->write("test");
        $resource = $stream->detach();
        $this->assertIsResource($resource);

        $stream->close();
        $this->assertIsResource($resource);
    }

    public function testDetachedToString(): void
    {
        $stream = new Stream(fopen("php://memory", "r+"));
        $stream->write("test");
        $this->assertIsResource($stream->detach());

        $this->assertEquals("", (string)$stream);
    }

    public function testDetachedGetSize(): void
    {
        $stream = new Stream(fopen("php://memory", "r+"));
        $stream->write("test");
        $this->assertIsResource($stream->detach());

        $this->assertNull($stream->getSize());
    }

    public function testDetachedTell(): void
    {
        $stream = new Stream(fopen("php://memory", "r+"));
        $stream->write("test");
        $this->assertIsResource($stream->detach());

        $this->expectException(RuntimeException::class);
        $stream->tell();
    }

    public function testDetachedEof(): void
    {
        $stream = new Stream(fopen("php://memory", "r+"));
        $stream->write("test");
        $this->assertIsResource($stream->detach());

        $this->assertTrue($stream->eof());
    }

    public function testDetachedGetMeta(): void
    {
        $stream = new Stream(fopen("php://memory", "r+"));
        $stream->write("test");
        $this->assertIsResource($stream->detach());

        $this->assertEquals([], $stream->getMetadata());
        $this->assertNull($stream->getMetadata("mode"));
    }

    public function testGetContentFails(): void
    {
        $resource = fopen("php://memory", "r+");
        $stream = new Stream($resource);
        $refObject = new ReflectionObject($stream);
        $refObject->getProperty("resource")
            ->setValue($stream, fopen($this->tmpPath, "w"));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Failed to read from stream");
        $stream->getContents();
    }

    public function testToStringFails(): void
    {
        $resource = fopen("php://memory", "r+");
        $stream = new Stream($resource);
        $refObject = new ReflectionObject($stream);
        $refObject->getProperty("resource")
            ->setValue($stream, fopen($this->tmpPath, "w"));

        $this->assertEquals("", (string)$stream);
    }

    public function testReadFails(): void
    {
        $resource = fopen("php://memory", "r+");
        $stream = new Stream($resource);
        $refObject = new ReflectionObject($stream);
        $refObject->getProperty("resource")
            ->setValue($stream, fopen($this->tmpPath, "w"));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Failed to read from stream");
        $stream->read(5);
    }

    public function testWriteFails(): void
    {
        $resource = fopen("php://memory", "r+");
        $stream = new Stream($resource);
        $refObject = new ReflectionObject($stream);
        $refObject->getProperty("resource")
            ->setValue($stream, fopen($this->tmpPath, "r"));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Failed to write to stream");
        $stream->write("test");
    }

    public function testTellFails(): void
    {
        $resource = fopen("/dev/null", "r+");
        $stream = new Stream($resource);

        $this->expectException(RuntimeException::class);
        $stream->tell();
    }

    public function testStatFails(): void
    {
        $resource = fopen("php://input", "r+");
        $stream = new Stream($resource);

        $this->assertNull($stream->getSize());
    }
}
