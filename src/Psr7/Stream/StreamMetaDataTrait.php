<?php

namespace Aternos\CurlPsr\Psr7\Stream;

trait StreamMetaDataTrait
{
    /**
     * @inheritDoc
     */
    public function getMetadata(?string $key = null): mixed
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
