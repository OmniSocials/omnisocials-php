<?php

declare(strict_types=1);

namespace OmniSocials\Exception;

/**
 * 429 Too Many Requests. The API allows 100 requests per minute per key.
 */
class RateLimitException extends ApiException
{
    public function __construct(
        string $message,
        int $status = 429,
        ?string $errorCode = null,
        ?array $body = null,
        private readonly ?int $retryAfter = null
    ) {
        parent::__construct($message, $status, $errorCode, $body);
    }

    /**
     * Seconds to wait before retrying, from the Retry-After header (if present).
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
