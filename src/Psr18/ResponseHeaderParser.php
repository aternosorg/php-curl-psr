<?php

namespace Aternos\CurlPsr\Psr18;

use Closure;
use Psr\Http\Message\ResponseInterface;

class ResponseHeaderParser
{
    protected array $headers = [];
    protected ?string $reason = null;

    /**
     * @return string|null
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * @param $_
     * @param string $rawHeader
     * @return int
     */
    protected function parseHeader($_, string $rawHeader): int
    {
        if (str_starts_with($rawHeader, "HTTP/") && !str_contains($rawHeader, ":")) {
            $parts = explode(" ", trim($rawHeader), 3);
            if (count($parts) < 3) {
                $this->reason = "";
            } else {
                $this->reason = $parts[2];
            }
            return strlen($rawHeader);
        }


        $parts = array_map(trim(...), explode(":", $rawHeader, 2));
        if (count($parts) !== 2) {
            return strlen($rawHeader);
        }

        [$name, $value] = $parts;

        if (!isset($this->headers[$name])) {
            $this->headers[$name] = [];
        }
        $this->headers[$name][] = $value;

        return strlen($rawHeader);
    }

    /**
     * @return Closure
     */
    public function getClosure(): Closure
    {
        return $this->parseHeader(...);
    }

    /**
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function applyToResponse(ResponseInterface $response): ResponseInterface
    {
        foreach ($this->headers as $name => $values) {
            $response = $response->withAddedHeader($name, $values);
        }
        return $response;
    }
}
