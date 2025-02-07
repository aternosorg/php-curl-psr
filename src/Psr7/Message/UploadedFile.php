<?php

namespace Aternos\CurlPsr\Psr7\Message;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;

class UploadedFile implements UploadedFileInterface
{
    protected bool $moved = false;

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

        $filePath = $this->stream->getMetadata('uri');
        if ($filePath !== null) {
            if (!@rename($filePath, $targetPath)) {
                throw new RuntimeException('Uploaded file could not be moved');
            }
            $this->moved = true;
            return;
        }

        if ($this->stream->tell() !== 0) {
            if (!$this->stream->isSeekable()) {
                throw new RuntimeException('Stream needs to be rewound, but is not seekable');
            }
            $this->stream->rewind();
        }

        $target = fopen($targetPath, 'wb');
        if ($target === false) {
            throw new RuntimeException('Target path could not be opened');
        }

        try {
            while (!$this->stream->eof()) {
                fwrite($target, $this->stream->read(4096));
            }
        } finally {
            fclose($target);
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
