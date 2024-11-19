<?php

namespace Aternos\CurlPsr\Psr7\Stream;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

class StringStream implements StreamInterface
{
    protected int $position = 0;

    /**
     * @param string $data
     * @param bool $seekable
     * @param bool $readable
     * @param bool $writable
     */
    public function __construct(
        protected string $data,
        protected bool $seekable = true,
        protected bool $readable = true,
        protected bool $writable = true
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        if (!$this->isReadable()) {
            return "";
        }
        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function close(): void
    {
    }

    /**
     * @inheritDoc
     */
    public function detach()
    {
        $this->data = "";
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getSize(): ?int
    {
        return strlen($this->data);
    }

    /**
     * @inheritDoc
     */
    public function tell(): int
    {
        return $this->position;
    }

    /**
     * @inheritDoc
     */
    public function eof(): bool
    {
        return $this->position >= $this->getSize();
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
        $this->position = match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->position + $offset,
            SEEK_END => $this->getSize() + $offset,
            default => throw new RuntimeException("Invalid whence"),
        };
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

        $size = strlen($string);
        $this->data = substr_replace($this->data, $string, $this->position, $size);
        $this->position += $size;
        return $size;
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

        $data = substr($this->data, $this->position, $length);
        $this->position += strlen($data);
        return $data;
    }

    /**
     * @inheritDoc
     */
    public function getContents(): string
    {
        if (!$this->isReadable()) {
            throw new RuntimeException("Stream is not readable");
        }
        return substr($this->data, $this->position);
    }

    /**
     * @return string|null
     */
    protected function approximateMode(): ?string
    {
        if ($this->isReadable() && $this->isWritable()) {
            return "r+";
        }
        if ($this->isReadable()) {
            return "r";
        }
        if ($this->isWritable()) {
            return "w";
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(?string $key = null)
    {
        if ($key !== null) {
            return match ($key) {
                "timed_out", "blocked" => false,
                "eof" => $this->eof(),
                "unread_bytes" => $this->getSize() - $this->tell(),
                "mode" => $this->approximateMode(),
                "seekable" => $this->isSeekable(),
                default => null
            };
        }

        return [
            "timed_out" => $this->getMetadata("timed_out"),
            "blocked" => $this->getMetadata("blocked"),
            "eof" => $this->getMetadata("eof"),
            "unread_bytes" => $this->getMetadata("unread_bytes"),
            "mode" => $this->getMetadata("mode"),
            "seekable" => $this->getMetadata("seekable"),
        ];
    }
}
