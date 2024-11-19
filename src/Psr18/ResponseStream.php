<?php

namespace Aternos\CurlPsr\Psr18;

use Aternos\CurlPsr\Curl\CurlHandleInterface;
use Aternos\CurlPsr\Exception\CloseStreamException;
use Fiber;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

class ResponseStream implements StreamInterface
{
    protected int $position = 0;

    /**
     * @param CurlHandleInterface $ch
     * @param Fiber $requestFiber
     * @param string $buffer
     * @param int|null $size
     */
    public function __construct(
        protected CurlHandleInterface $ch,
        protected Fiber               $requestFiber,
        protected string              $buffer = "",
        protected ?int                $size = null
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
        try {
            $this->requestFiber->throw(new CloseStreamException("Stream closed"));
        } catch (Throwable) {
            // Ignore
        }
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
        return $this->requestFiber->isTerminated() && strlen($this->buffer) === 0;
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
        throw new RuntimeException("Stream is not seekable");
    }

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        throw new RuntimeException("Stream is not seekable");
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
        while (!$this->requestFiber->isTerminated() && strlen($this->buffer) === 0) {
            try {
                $result = $this->requestFiber->resume();
            } catch (Throwable $e) {
                throw new RuntimeException("Failed to read from stream", 0, $e);
            }
            if ($result === null) {
                break;
            }
            $this->buffer = $result;
        }
        if ($this->ch->errno() !== CURLE_OK) {
            throw new RuntimeException("Failed to read from stream: " . $this->ch->error());
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
