<?php

declare(strict_types=1);

namespace OmniSocials\Exception;

/**
 * 403 Forbidden: the API key lacks a required scope (code "insufficient_scope").
 */
class PermissionDeniedException extends ApiException
{
}
