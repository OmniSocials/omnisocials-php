<?php

declare(strict_types=1);

namespace OmniSocials;

use OmniSocials\Exception\WebhookVerificationException;

/**
 * Verify OmniSocials webhook deliveries (Stripe-style scheme).
 *
 * The signed value is `"{timestamp}.{rawBody}"`, HMAC-SHA256 with the webhook
 * secret, hex digest; the `X-OmniSocials-Signature` header is `t=<unix>,v1=<hex>`.
 */
final class Webhooks
{
    public const DEFAULT_TOLERANCE_SECONDS = 300;

    private function __construct()
    {
    }

    /**
     * Verify a webhook delivery and return the parsed event array.
     *
     * Always pass the RAW request body exactly as received (for example
     * `file_get_contents('php://input')` or Laravel's `$request->getContent()`).
     * Do not decode and re-encode it first: the signature is computed over the
     * raw bytes.
     *
     * Uses a constant-time comparison (`hash_equals`) and rejects timestamps
     * older than $tolerance seconds (replay protection).
     *
     * @param string $payload   The raw request body.
     * @param string $signature Value of the X-OmniSocials-Signature header: `t=<unix>,v1=<hex>`.
     * @param string $secret    The webhook's signing secret (shown once on create / rotate-secret).
     * @param int    $tolerance Max allowed age of the timestamp, in seconds. Defaults to 300 (5 minutes).
     *
     * @return array<string, mixed> The parsed event object.
     *
     * @throws WebhookVerificationException on any failure.
     */
    public static function verifySignature(
        string $payload,
        string $signature,
        string $secret,
        int $tolerance = self::DEFAULT_TOLERANCE_SECONDS
    ): array {
        if ($secret === '') {
            throw new WebhookVerificationException('No webhook secret provided.');
        }
        if ($signature === '') {
            throw new WebhookVerificationException(
                'No signature header provided. Expected the X-OmniSocials-Signature header value.'
            );
        }

        // Parse `t=<unix>,v1=<hex>` (tolerate extra/unknown pairs and multiple v1).
        $timestampRaw = null;
        $candidateSignatures = [];
        foreach (explode(',', $signature) as $part) {
            $eq = strpos($part, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($part, 0, $eq));
            $value = trim(substr($part, $eq + 1));
            if ($key === 't') {
                $timestampRaw = $value;
            } elseif ($key === 'v1') {
                $candidateSignatures[] = $value;
            }
        }

        if ($timestampRaw === null || preg_match('/^-?\d+$/', $timestampRaw) !== 1) {
            throw new WebhookVerificationException(
                'Unable to extract timestamp from signature header. Expected format: t=<unix>,v1=<hex>.'
            );
        }
        if ($candidateSignatures === []) {
            throw new WebhookVerificationException(
                'Unable to extract v1 signature from signature header. Expected format: t=<unix>,v1=<hex>.'
            );
        }

        $expected = hash_hmac('sha256', $timestampRaw . '.' . $payload, $secret);

        $matches = false;
        foreach ($candidateSignatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                $matches = true;
            }
        }
        if (!$matches) {
            throw new WebhookVerificationException(
                'Webhook signature verification failed: no v1 signature matches the expected signature.'
            );
        }

        $timestamp = (int) $timestampRaw;
        $now = time();
        if ($tolerance > 0 && ($now - $timestamp) > $tolerance) {
            throw new WebhookVerificationException(sprintf(
                'Webhook timestamp is outside the allowed tolerance of %ds (event is %ds old). Possible replay.',
                $tolerance,
                $now - $timestamp
            ));
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) {
            throw new WebhookVerificationException(
                'Webhook payload is not valid JSON (did you pass the raw request body?).'
            );
        }

        return $event;
    }
}
