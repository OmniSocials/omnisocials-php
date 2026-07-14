<?php

declare(strict_types=1);

namespace OmniSocials\Exception;

/**
 * A non-2xx HTTP response from the OmniSocials API.
 *
 * `getErrorCode()` and `getMessage()` come from the API error body
 * `{ "error": { "code", "message" } }` when present. `getBody()` returns the
 * full parsed response body. `getStatus()` (also mirrored into
 * `getCode()`) is the HTTP status code.
 */
class ApiException extends OmniSocialsException
{
    public function __construct(
        string $message,
        private readonly int $status = 0,
        private readonly ?string $errorCode = null,
        private readonly ?array $body = null
    ) {
        parent::__construct($message, $status);
    }

    /**
     * HTTP status code of the failed response.
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Machine-readable error code from the API body (e.g. "validation_error").
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * The parsed JSON response body, when the API returned JSON.
     */
    public function getBody(): ?array
    {
        return $this->body;
    }

    /**
     * Map an HTTP status to the most specific ApiException subclass.
     */
    public static function create(
        int $status,
        string $message,
        ?string $errorCode = null,
        ?array $body = null,
        ?int $retryAfter = null
    ): self {
        return match (true) {
            $status === 400 || $status === 422 => new ValidationException($message, $status, $errorCode, $body),
            $status === 401 => new AuthenticationException($message, $status, $errorCode, $body),
            $status === 403 => new PermissionDeniedException($message, $status, $errorCode, $body),
            $status === 404 => new NotFoundException($message, $status, $errorCode, $body),
            $status === 429 => new RateLimitException($message, $status, $errorCode, $body, $retryAfter),
            $status >= 500 => new ServerException($message, $status, $errorCode, $body),
            default => new self($message, $status, $errorCode, $body),
        };
    }
}
