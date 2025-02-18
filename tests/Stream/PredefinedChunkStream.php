<?php

namespace Tests\Stream;

use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

class PredefinedChunkStream implements StreamInterface
{
    protected int $position = 0;
    protected string $buffer = "";

    /**
     * @param array $chunks
     * @param int|null $size
     */
    public function __construct(
        protected array $chunks,
        protected ?int $size = null
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        try {
            return $this->getContents();
        } catch (RuntimeException) {
            return "";
        }
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
        return $this->size;
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
        return count($this->chunks) === 0 && strlen($this->buffer) === 0;
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
        if (strlen($this->buffer) === 0 && count($this->chunks) > 0) {
            $chunk = array_shift($this->chunks);
            if ($chunk instanceof Throwable) {
                throw $chunk;
            }
            $this->buffer = $chunk;
        }

        $chunk = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, $length);
        $this->position += strlen($chunk);
        return $chunk;
    }

    /**
     * @inheritDoc
     */
    public function getContents(): string
    {
        $contents = "";
        while (!$this->eof()) {
            $contents .= $this->read(8192);
        }
        return $contents;
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
}
