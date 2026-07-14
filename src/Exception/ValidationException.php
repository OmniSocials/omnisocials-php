<?php

declare(strict_types=1);

namespace OmniSocials\Exception;

/**
 * 400 / 422: the request was rejected by validation (code "validation_error",
 * "platform_not_connected", "invalid_file_type", ...).
 */
class ValidationException extends ApiException
{
}
