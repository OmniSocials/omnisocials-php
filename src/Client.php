<?php

declare(strict_types=1);

namespace OmniSocials;

use OmniSocials\Exception\ApiConnectionException;
use OmniSocials\Exception\ApiException;
use OmniSocials\Exception\AuthenticationException;
use OmniSocials\Exception\OmniSocialsException;

/**
 * The OmniSocials API client.
 *
 *     $client = new \OmniSocials\Client();                      // reads OMNISOCIALS_API_KEY from env
 *     $client = new \OmniSocials\Client(apiKey: 'omsk_live_...');
 *
 * Resources are exposed as public properties (Stripe-PHP style):
 *
 *     $client->posts->create(['content' => 'Hello', 'channels' => ['instagram']]);
 *     $client->media->uploadFromUrl(['url' => 'https://example.com/pic.jpg']);
 *     $client->ads->drafts->create([...]);
 *
 * All methods return the parsed response body as-is (the API wraps payloads in
 * `{ data, pagination?, ... }`); endpoints that respond 204 return null.
 */
class Client
{
    public const VERSION = '0.18.0';
    public const DEFAULT_BASE_URL = 'https://api.omnisocials.com/v1';
    public const DEFAULT_TIMEOUT = 30.0;
    public const DEFAULT_MAX_RETRIES = 2;

    /**
     * Pass as `$retry` to retry only a 429. A call that can start ad spend
     * uses it: after a 5xx, a timeout or a lost connection the first attempt
     * may have gone through, so it is never sent again.
     */
    public const RETRY_RATE_LIMIT = 'rate_limit';

    public readonly Resource\Posts $posts;
    public readonly Resource\Media $media;
    public readonly Resource\Folders $folders;
    public readonly Resource\HashtagSets $hashtagSets;
    public readonly Resource\Tags $tags;
    public readonly Resource\ApprovalWorkflows $approvalWorkflows;
    public readonly Resource\Accounts $accounts;
    public readonly Resource\Analytics $analytics;
    public readonly Resource\Credits $credits;
    public readonly Resource\Audio $audio;
    public readonly Resource\Locations $locations;
    public readonly Resource\Pinterest $pinterest;
    public readonly Resource\Automations $automations;
    public readonly Resource\Ads $ads;
    public readonly Resource\Inbox $inbox;
    public readonly Resource\Webhooks $webhooks;
    public readonly Resource\Limits $limits;
    public readonly Resource\FeatureRequests $featureRequests;

    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly float $timeout;
    private readonly int $maxRetries;

    /**
     * @param string|null $apiKey     API key (`omsk_live_*`).
     *                                Defaults to the OMNISOCIALS_API_KEY environment variable.
     * @param string|null $baseUrl    API base URL. Defaults to https://api.omnisocials.com/v1.
     * @param float|null  $timeout    Per-request timeout in seconds. Defaults to 30.
     * @param int|null    $maxRetries Automatic retries on 429 / 5xx / connection errors. Defaults to 2.
     *
     * @throws AuthenticationException when no API key is provided or found in the environment.
     */
    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?float $timeout = null,
        ?int $maxRetries = null
    ) {
        $key = $apiKey ?? (getenv('OMNISOCIALS_API_KEY') ?: null);

        if ($key === null || $key === '') {
            throw new AuthenticationException(
                'No API key provided. Pass one with `new OmniSocials\\Client(apiKey: \'omsk_live_...\')` '
                . 'or set the OMNISOCIALS_API_KEY environment variable. Create a key in the '
                . 'OmniSocials app under Settings -> API Keys.',
                401,
                'missing_api_key'
            );
        }

        $this->apiKey = $key;
        $this->baseUrl = rtrim($baseUrl ?? self::DEFAULT_BASE_URL, '/');
        $this->timeout = $timeout ?? self::DEFAULT_TIMEOUT;
        $this->maxRetries = $maxRetries ?? self::DEFAULT_MAX_RETRIES;

        $this->posts = new Resource\Posts($this);
        $this->media = new Resource\Media($this);
        $this->folders = new Resource\Folders($this);
        $this->hashtagSets = new Resource\HashtagSets($this);
        $this->tags = new Resource\Tags($this);
        $this->approvalWorkflows = new Resource\ApprovalWorkflows($this);
        $this->accounts = new Resource\Accounts($this);
        $this->analytics = new Resource\Analytics($this);
        $this->credits = new Resource\Credits($this);
        $this->audio = new Resource\Audio($this);
        $this->locations = new Resource\Locations($this);
        $this->pinterest = new Resource\Pinterest($this);
        $this->automations = new Resource\Automations($this);
        $this->ads = new Resource\Ads($this);
        $this->inbox = new Resource\Inbox($this);
        $this->webhooks = new Resource\Webhooks($this);
        $this->limits = new Resource\Limits($this);
        $this->featureRequests = new Resource\FeatureRequests($this);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    /**
     * `GET /health` (no scopes required).
     */
    public function health(): mixed
    {
        return $this->get('/health');
    }

    // HTTP verb helpers used by resources.

    public function get(string $path, array $query = []): mixed
    {
        return $this->request('GET', $path, $query);
    }

    public function post(string $path, ?array $body = null, ?string $retry = null): mixed
    {
        return $this->request('POST', $path, [], $body, null, $retry);
    }

    public function postMultipart(string $path, array $fields): mixed
    {
        return $this->request('POST', $path, [], null, $fields);
    }

    public function patch(string $path, array $body, ?string $retry = null): mixed
    {
        return $this->request('PATCH', $path, [], $body, null, $retry);
    }

    public function put(string $path, array $body, ?string $retry = null): mixed
    {
        return $this->request('PUT', $path, [], $body, null, $retry);
    }

    /**
     * DELETE, optionally with query params (for example `ad_account_id`) or
     * a JSON body (removing people from a customer list).
     *
     * @param array<string, mixed>      $query
     * @param array<string, mixed>|null $body
     */
    public function delete(string $path, array $query = [], ?array $body = null): mixed
    {
        return $this->request('DELETE', $path, $query, $body);
    }

    /**
     * Core request loop: builds the URL, sends the request via cURL with a
     * timeout, and retries 429 / 5xx / connection errors with exponential
     * backoff + jitter (honoring Retry-After when present). Returns the parsed
     * response body as-is; 204 returns null.
     *
     * @param array<string, mixed>      $query     Query params; null values are dropped.
     * @param array<string, mixed>|null $body      JSON request body (null keys are kept:
     *                                             e.g. `['thread_parts' => null]` clears a thread).
     * @param array<string, mixed>|null $multipart Multipart form fields (mutually exclusive with $body).
     * @param string|null               $retry     `self::RETRY_RATE_LIMIT` retries only a 429 (the ads
     *                                             calls that can start or raise spend).
     *
     * @throws ApiException           on a non-2xx response (most specific subclass).
     * @throws ApiConnectionException on network failure or timeout.
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        ?array $multipart = null,
        ?string $retry = null
    ): mixed {
        $url = $this->buildUrl($path, $query);
        $jsonBody = null;
        if ($multipart === null && $body !== null) {
            $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($jsonBody === false) {
                throw new ApiConnectionException(
                    'Unable to JSON-encode the request body: ' . json_last_error_msg()
                );
            }
        }

        $attempt = 0;
        while (true) {
            [$status, $responseBody, $responseHeaders, $errno, $error] = $this->executeOnce(
                $method,
                $url,
                $jsonBody,
                $multipart
            );

            if ($errno !== 0) {
                // Network failure or timeout: retryable, unless the call may
                // have started ad spend.
                if ($retry !== self::RETRY_RATE_LIMIT && $attempt < $this->maxRetries) {
                    $this->sleepBackoff($attempt);
                    $attempt++;
                    continue;
                }
                $message = $errno === CURLE_OPERATION_TIMEDOUT
                    ? sprintf('Request timed out after %.0fs (%s %s).', $this->timeout, $method, $url)
                    : sprintf('Connection error (%s %s): %s', $method, $url, $error);
                throw new ApiConnectionException($message, $errno);
            }

            if ($status >= 200 && $status < 300) {
                if ($status === 204 || $responseBody === '') {
                    return null;
                }
                $decoded = json_decode($responseBody, true);
                if ($decoded === null && trim($responseBody) !== 'null') {
                    return $responseBody; // Non-JSON 2xx body: return the raw text.
                }
                return $decoded;
            }

            $retryAfter = $this->parseRetryAfter($responseHeaders['retry-after'] ?? null);
            $retryable = $status === 429 || ($status >= 500 && $retry !== self::RETRY_RATE_LIMIT);
            if ($retryable && $attempt < $this->maxRetries) {
                $this->sleepBackoff($attempt, $retryAfter);
                $attempt++;
                continue;
            }

            throw $this->responseToException($status, $responseBody, $retryAfter);
        }
    }

    // Internals.

    private function buildUrl(string $path, array $query): string
    {
        $path = str_starts_with($path, '/') ? $path : '/' . $path;
        // Every id in $path was encoded with rawurlencode, which leaves "." and
        // ".." as they are: URL normalisation drops such a segment or climbs
        // one level, so an id of ".." would reach another endpoint of the same
        // API key, and an empty id the list route. Refused before sending.
        $segments = array_slice(explode('/', explode('?', $path, 2)[0]), 1);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new OmniSocialsException(sprintf(
                    'Invalid request path %s: an id cannot be empty, "." or "..".',
                    json_encode($path, JSON_UNESCAPED_SLASHES)
                ));
            }
        }
        $url = $this->baseUrl . $path;
        $pairs = [];
        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $pairs[$key] = (string) $value;
        }
        if ($pairs !== []) {
            $url .= '?' . http_build_query($pairs, '', '&', PHP_QUERY_RFC3986);
        }
        return $url;
    }

    /**
     * Perform a single HTTP request attempt.
     *
     * @return array{0: int, 1: string, 2: array<string, string>, 3: int, 4: string}
     *         [status, body, lowercased response headers, curl errno, curl error message]
     */
    private function executeOnce(string $method, string $url, ?string $jsonBody, ?array $multipart): array
    {
        $responseHeaders = [];
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => (int) round($this->timeout * 1000),
            CURLOPT_USERAGENT => 'omnisocials-php/' . self::VERSION,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);

        if ($multipart !== null) {
            // Let cURL set the multipart/form-data boundary Content-Type itself.
            curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart);
        } elseif ($jsonBody !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $responseBody = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        // No curl_close(): PHP 8.0+ frees the handle when $ch goes out of
        // scope, and PHP 8.5 deprecates the call.

        return [
            $status,
            is_string($responseBody) ? $responseBody : '',
            $responseHeaders,
            $errno,
            $error,
        ];
    }

    /**
     * Parse a Retry-After header value (delta-seconds or HTTP-date) to seconds.
     */
    private function parseRetryAfter(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            $seconds = (int) $value;
            return $seconds >= 0 ? $seconds : null;
        }
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            $diff = $timestamp - time();
            return $diff > 0 ? $diff : 0;
        }
        return null;
    }

    /**
     * Exponential backoff (0.5s, 1s, 2s, ...) with jitter; Retry-After wins.
     */
    private function sleepBackoff(int $attempt, ?int $retryAfterSeconds = null): void
    {
        if ($retryAfterSeconds !== null) {
            $seconds = (float) min($retryAfterSeconds, 60);
        } else {
            $base = 0.5 * (2 ** $attempt);
            $jitter = 0.75 + (mt_rand() / mt_getrandmax()) * 0.5; // 75% - 125%
            $seconds = min($base * $jitter, 30.0);
        }
        usleep((int) round($seconds * 1_000_000));
    }

    private function responseToException(int $status, string $responseBody, ?int $retryAfter): ApiException
    {
        $parsed = json_decode($responseBody, true);
        $code = null;
        $message = sprintf('Request failed with status %d.', $status);

        if (is_array($parsed)) {
            $error = $parsed['error'] ?? null;
            if (is_array($error)) {
                if (isset($error['code']) && is_string($error['code'])) {
                    $code = $error['code'];
                }
                if (isset($error['message']) && is_string($error['message'])) {
                    $message = $error['message'];
                }
            } elseif (is_string($error)) {
                $message = $error;
            }
        }

        return ApiException::create($status, $message, $code, is_array($parsed) ? $parsed : null, $retryAfter);
    }
}
