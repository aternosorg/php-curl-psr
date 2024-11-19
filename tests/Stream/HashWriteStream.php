<?php

namespace Tests\Stream;

use HashContext;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Class HashWriteStream
 * A writable stream that discards all data but hashes it
 */
class HashWriteStream implements StreamInterface
{
    protected int $position = 0;
    protected HashContext $hashContext;

    static function readFrom(StreamInterface $stream): string
    {
        $hashStream = new static();
        while (!$stream->eof()) {
            $hashStream->write($stream->read(8192));
        }
        return $hashStream->getFinalHash();
    }

    /**
     * @param string $hashAlgorithm
     */
    public function __construct(
        protected string $hashAlgorithm = "sha1"
    )
    {
        $this->hashContext = hash_init($this->hashAlgorithm);
    }

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
    public function detach()
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getSize(): ?int
    {
        return null;
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
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isSeekable(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new RuntimeException("Not seekable");
    }

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        throw new RuntimeException("Not seekable");
    }

    /**
     * @inheritDoc
     */
    public function isWritable(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function write(string $string): int
    {
        hash_update($this->hashContext, $string);
        $this->position += strlen($string);
        return strlen($string);
    }

    /**
     * @inheritDoc
     */
    public function isReadable(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function read(int $length): string
    {
        throw new RuntimeException("Not readable");
    }

    /**
     * @inheritDoc
     */
    public function getContents(): string
    {
        throw new RuntimeException("Not readable");
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(?string $key = null)
    {
        if ($key === null) {
            return [];
        }
        return null;
    }

    /**
     * @return string
     */
    public function getFinalHash(): string
    {
        return hash_final($this->hashContext);
    }
}
