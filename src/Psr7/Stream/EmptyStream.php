<?php

namespace Aternos\CurlPsr\Psr7\Stream;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

class EmptyStream implements StreamInterface
{
    use StreamMetaDataTrait;

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return "";
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
    public function detach(): null
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getSize(): ?int
    {
        return 0;
    }

    /**
     * @inheritDoc
     */
    public function tell(): int
    {
        return 0;
    }

    /**
     * @inheritDoc
     */
    public function eof(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isSeekable(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
    }

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
    }

    /**
     * @inheritDoc
     */
    public function isWritable(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function write(string $string): int
    {
        throw new RuntimeException("Stream is not writable");
    }

    /**
     * @inheritDoc
     */
    public function isReadable(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function read(int $length): string
    {
        return "";
    }

    /**
     * @inheritDoc
     */
    public function getContents(): string
    {
        return "";
    }
}
