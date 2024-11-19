<?php

namespace Tests\Stream;

use HashContext;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Class RandomDataStream
 * A stream of a specified number of random bytes
 * Keeps a hash of the data for testing purposes
 */
class RandomDataStream implements StreamInterface
{
    protected int $position = 0;
    protected HashContext $hashContext;

    /**
     * @param int $size
     * @param bool $knownSize
     * @param string $hashAlgorithm
     */
    public function __construct(
        protected int $size,
        protected bool $knownSize = true,
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
        return $this->getContents();
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
        if ($this->knownSize) {
            return $this->size;
        }
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
        return $this->position >= $this->size;
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
        return false;
    }

    /**
     * @inheritDoc
     */
    public function write(string $string): int
    {
        throw new RuntimeException("Not writable");
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
        $length = min($length, $this->size - $this->position);
        if ($length <= 0) {
            return "";
        }
        $length = rand(1, $length);
        $data = random_bytes($length);
        hash_update($this->hashContext, $data);
        $this->position += $length;
        return $data;
    }

    /**
     * @inheritDoc
     */
    public function getContents(): string
    {
        $data = "";
        while (!$this->eof()) {
            $data .= $this->read(8192);
        }
        return $data;
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
