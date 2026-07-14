<?php

declare(strict_types=1);

namespace OmniSocials\Exception;

/**
 * 401 Unauthorized: missing, invalid, or revoked API key. Also thrown at
 * construction time when no API key is provided.
 */
class AuthenticationException extends ApiException
{
}
