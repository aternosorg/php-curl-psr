<?php

namespace Aternos\CurlPsr\Psr7\Stream;

use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

class Stream implements StreamInterface
{
    protected const array READABLE_MODES = [
        "r", "w+", "r+", "x+",
        "c+", "rb", "w+b", "r+b",
        "x+b", "c+b", "rt", "w+t",
        "r+t", "x+t", "c+t", "a+"
    ];

    protected const array WRITABLE_MODES = [
        "w", "w+", "rw", "r+",
        "x+", "c+", "wb", "w+b",
        "r+b", "x+b", "c+b", "w+t",
        "r+t", "x+t", "c+t", "a",
        "a+"
    ];

    protected bool $seekable = false;
    protected bool $readable = false;
    protected bool $writable = false;

    /**
     * @param resource $resource
     */
    public function __construct(
        protected $resource
    )
    {
        $meta = stream_get_meta_data($this->resource);
        $this->seekable = $meta['seekable'] && @fseek($this->resource, 0, SEEK_CUR) === 0;
        $this->readable = in_array($meta['mode'], static::READABLE_MODES, true);
        $this->writable = in_array($meta['mode'], static::WRITABLE_MODES, true);
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        if (!is_resource($this->resource)) {
            return "";
        }

        if ($this->isSeekable()) {
            $this->rewind();
        }
        try {
            return $this->getContents();
        } catch (Throwable) {
            return "";
        }
    }

    /**
     * @inheritDoc
     */
    public function close(): void
    {
        if (!is_resource($this->resource)) {
            return;
        }
        fclose($this->resource);
    }

    /**
     * @inheritDoc
     */
    public function detach()
    {
        if (!is_resource($this->resource)) {
            return null;
        }
        $resource = $this->resource;
        $this->resource = null;
        $this->seekable = false;
        $this->readable = false;
        $this->writable = false;
        return $resource;
    }

    /**
     * @inheritDoc
     */
    public function getSize(): ?int
    {
        if (!is_resource($this->resource)) {
            return null;
        }
        $stats = fstat($this->resource);
        if ($stats === false) {
            return null;
        }
        return $stats['size'];
    }

    /**
     * @inheritDoc
     */
    public function tell(): int
    {
        if (!is_resource($this->resource)) {
            throw new RuntimeException("Stream is detached");
        }
        $result = ftell($this->resource);
        if ($result === false) {
            throw new RuntimeException("Failed to determine stream position");
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function eof(): bool
    {
        if (!is_resource($this->resource)) {
            return true;
        }
        return feof($this->resource);
    }

    /**
     * @inheritDoc
     */
    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    /**
     * @inheritDoc
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->isSeekable()) {
            throw new RuntimeException("Stream is not seekable");
        }
        if (fseek($this->resource, $offset, $whence) === -1) {
            throw new RuntimeException("Failed to seek to stream position");
        }
    }

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * @inheritDoc
     */
    public function isWritable(): bool
    {
        return $this->writable;
    }

    /**
     * @inheritDoc
     */
    public function write(string $string): int
    {
        if (!$this->isWritable()) {
            throw new RuntimeException("Stream is not writable");
        }
        $result = fwrite($this->resource, $string);
        if ($result === false) {
            throw new RuntimeException("Failed to write to stream");
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function isReadable(): bool
    {
        return $this->readable;
    }

    /**
     * @inheritDoc
     */
    public function read(int $length): string
    {
        if (!$this->isReadable()) {
            throw new RuntimeException("Stream is not readable");
        }
        if ($length <= 0) {
            return "";
        }
        $result = fread($this->resource, $length);
        if ($result === false) {
            throw new RuntimeException("Failed to read from stream");
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getContents(): string
    {
        $content = "";
        while (!$this->eof()) {
            $content .= $this->read(8192);
        }
        return $content;
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(?string $key = null)
    {
        if (!is_resource($this->resource)) {
            if ($key === null) {
                return [];
            }
            return null;
        }

        $meta = stream_get_meta_data($this->resource);
        if ($key === null) {
            return $meta;
        }
        return $meta[$key] ?? null;
    }
}
