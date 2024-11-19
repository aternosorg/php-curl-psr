<?php

namespace Aternos\CurlPsr\Psr7\Message;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

class UploadedFile implements UploadedFileInterface
{
    protected bool $moved = false;
    protected string $filePath;

    /**
     * @param StreamInterface $stream
     * @param int|null $size
     * @param int $error
     * @param string|null $clientFilename
     * @param string|null $clientMediaType
     */
    public function __construct(
        protected StreamInterface $stream,
        protected ?int            $size = null,
        protected int             $error = UPLOAD_ERR_OK,
        protected ?string         $clientFilename = null,
        protected ?string         $clientMediaType = null
    )
    {
        $file = $this->stream->getMetadata('uri');
        if ($file === null) {
            throw new InvalidArgumentException('Stream uri not available');
        }
        $this->filePath = $file;
    }

    /**
     * @inheritDoc
     */
    public function getStream(): StreamInterface
    {
        return $this->stream;
    }

    /**
     * @inheritDoc
     */
    public function moveTo(string $targetPath): void
    {
        if ($this->moved) {
            throw new RuntimeException('Uploaded file already moved');
        }
        if (!@rename($this->filePath, $targetPath)) {
            throw new RuntimeException('Uploaded file could not be moved');
        }
        $this->moved = true;
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
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * @inheritDoc
     */
    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    /**
     * @inheritDoc
     */
    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }
}
