<?php

declare(strict_types=1);

namespace OmniSocials\Exception;

/**
 * Webhook signature verification failed (bad signature, stale timestamp,
 * malformed header, or invalid payload).
 */
class WebhookVerificationException extends OmniSocialsException
{
}
