<?php

namespace Tests;

use Aternos\CurlPsr\Psr17\Psr17Factory;
use Aternos\CurlPsr\Psr7\Stream\StringStream;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class UploadedFileTest extends TestCase
{
    protected Psr17Factory $factory;
    protected ?string $tmpPath = null;
    protected ?string $target = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new Psr17Factory();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->tmpPath !== null && file_exists($this->tmpPath)) {
            unlink($this->tmpPath);
        }
        if ($this->target !== null && file_exists($this->target)) {
            unlink($this->target);
        }
    }

    public function testUploadedFile(): void
    {
        $this->tmpPath = tempnam(sys_get_temp_dir(), 'test');
        $this->target = $this->tmpPath . '_target';
        file_put_contents($this->tmpPath, 'test');
        $stream = $this->factory->createStreamFromFile($this->tmpPath);

        $uploadedFile = $this->factory->createUploadedFile($stream, filesize($this->tmpPath), UPLOAD_ERR_OK, basename($this->tmpPath), 'text/plain');
        $this->assertInstanceOf(StreamInterface::class, $uploadedFile->getStream());
        $this->assertEquals(filesize($this->tmpPath), $uploadedFile->getSize());
        $this->assertEquals(basename($this->tmpPath), $uploadedFile->getClientFilename());
        $this->assertEquals('text/plain', $uploadedFile->getClientMediaType());
        $this->assertEquals(UPLOAD_ERR_OK, $uploadedFile->getError());
        $uploadedFile->moveTo($this->target);
        $this->assertFileExists($this->target);
        $this->assertEquals('test', file_get_contents($this->target));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Uploaded file already moved');
        $uploadedFile->moveTo($this->target);
    }

    public function testThrowWhenMovedToInvalidTarget(): void
    {
        $this->tmpPath = tempnam(sys_get_temp_dir(), 'test');
        $target = sys_get_temp_dir();
        file_put_contents($this->tmpPath, 'test');
        $stream = $this->factory->createStreamFromFile($this->tmpPath);

        $uploadedFile = $this->factory->createUploadedFile($stream, filesize($this->tmpPath), UPLOAD_ERR_OK, basename($this->tmpPath), 'text/plain');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Uploaded file could not be moved');
        $uploadedFile->moveTo($target);
    }

    public function testWriteStreamContentIfStreamIsNotAFile(): void
    {
        $stream = $this->factory->createStream('test');
        $upload = $this->factory->createUploadedFile($stream, $stream->getSize(), UPLOAD_ERR_OK, "file.txt", "text/plain");

        $this->target = tempnam(sys_get_temp_dir(), 'test');
        $upload->moveTo($this->target);

        $this->assertFileExists($this->target);
        $this->assertEquals('test', file_get_contents($this->target));
    }

    public function testThrowIfWriteTargetCannotBeOpened(): void
    {
        $stream = $this->factory->createStream('test');
        $upload = $this->factory->createUploadedFile($stream, $stream->getSize(), UPLOAD_ERR_OK, "file.txt", "text/plain");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Target path could not be opened');
        $upload->moveTo(sys_get_temp_dir() . "/non-existing-dir/file.txt");
    }

    public function testRewindStreamIfNecessary(): void
    {
        $stream = $this->factory->createStream('test');
        $stream->read(1);
        $upload = $this->factory->createUploadedFile($stream, $stream->getSize(), UPLOAD_ERR_OK, "file.txt", "text/plain");

        $this->target = tempnam(sys_get_temp_dir(), 'test');
        $upload->moveTo($this->target);

        $this->assertFileExists($this->target);
        $this->assertEquals('test', file_get_contents($this->target));
    }

    public function testThrowIfStreamNeedsRewindButIsNotSeekable(): void
    {
        $stream = new StringStream('test', false);
        $stream->read(1);
        $upload = $this->factory->createUploadedFile($stream, $stream->getSize(), UPLOAD_ERR_OK, "file.txt", "text/plain");
        $this->target = tempnam(sys_get_temp_dir(), 'test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stream needs to be rewound, but is not seekable');
        $upload->moveTo($this->target);
    }
}
