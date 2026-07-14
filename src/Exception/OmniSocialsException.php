<?php

declare(strict_types=1);

namespace OmniSocials\Exception;

/**
 * Base class for every exception thrown by the OmniSocials SDK.
 *
 *   OmniSocialsException              (base for everything the SDK throws)
 *     ApiException                    (non-2xx HTTP response; has status/code/body)
 *       AuthenticationException       (401)
 *       PermissionDeniedException     (403)
 *       NotFoundException             (404)
 *       ValidationException           (400 / 422)
 *       RateLimitException            (429; exposes getRetryAfter() seconds)
 *       ServerException               (>= 500)
 *     ApiConnectionException          (network failure / timeout)
 *     WebhookVerificationException    (invalid webhook signature)
 */
class OmniSocialsException extends \Exception
{
}
