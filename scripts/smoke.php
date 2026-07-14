<?php

/**
 * Smoke test for the OmniSocials PHP SDK. No composer install needed:
 * a tiny PSR-4 autoloader is registered inline. Run with:
 *
 *   php scripts/smoke.php
 *
 * Verifies: client construction, env fallback, resource method surface
 * (every endpoint in the DESIGN.md inventory), the exception hierarchy, and
 * a webhook signature round-trip (same scheme as backend
 * webhookDispatcher.js: HMAC-SHA256 hex over "{ts}.{body}", header
 * "t=<ts>,v1=<hex>").
 */

declare(strict_types=1);

error_reporting(E_ALL);

// Inline PSR-4 autoloader: OmniSocials\ -> ../src/
spl_autoload_register(static function (string $class): void {
    $prefix = 'OmniSocials\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

use OmniSocials\Client;
use OmniSocials\Exception\ApiConnectionException;
use OmniSocials\Exception\ApiException;
use OmniSocials\Exception\AuthenticationException;
use OmniSocials\Exception\NotFoundException;
use OmniSocials\Exception\OmniSocialsException;
use OmniSocials\Exception\PermissionDeniedException;
use OmniSocials\Exception\RateLimitException;
use OmniSocials\Exception\ServerException;
use OmniSocials\Exception\ValidationException;
use OmniSocials\Exception\WebhookVerificationException;
use OmniSocials\Webhooks;

$failures = 0;

function check(bool $condition, string $label): void
{
    global $failures;
    if ($condition) {
        echo "  ok: {$label}\n";
    } else {
        $failures++;
        echo "  FAIL: {$label}\n";
    }
}

/**
 * @param class-string<\Throwable> $expectedClass
 */
function checkThrows(callable $fn, string $expectedClass, string $label): void
{
    global $failures;
    try {
        $fn();
        $failures++;
        echo "  FAIL: {$label} (nothing thrown)\n";
    } catch (\Throwable $e) {
        if ($e instanceof $expectedClass) {
            echo "  ok: {$label}\n";
        } else {
            $failures++;
            echo "  FAIL: {$label} (threw " . get_class($e) . ": {$e->getMessage()})\n";
        }
    }
}

echo "== Client construction and env fallback ==\n";

// Structural version check: the constant must match composer.json, so this
// script never needs stamping on release (set-version.mjs syncs both).
$composerJson = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);
check(Client::VERSION === $composerJson['version'], 'Client::VERSION matches composer.json (' . $composerJson['version'] . ')');

// Missing key (no arg, no env) throws AuthenticationException at construction.
$savedKey = getenv('OMNISOCIALS_API_KEY');
putenv('OMNISOCIALS_API_KEY'); // unset
checkThrows(
    static fn () => new Client(),
    AuthenticationException::class,
    'missing API key throws AuthenticationException'
);

// Env fallback works and getenv() is used.
putenv('OMNISOCIALS_API_KEY=omsk_test_env_fallback');
$envClient = new Client();
$apiKeyProp = new ReflectionProperty(Client::class, 'apiKey');
check(
    $apiKeyProp->getValue($envClient) === 'omsk_test_env_fallback',
    'env fallback picks up OMNISOCIALS_API_KEY'
);

// Constructor arg wins over the env var.
$argClient = new Client(apiKey: 'omsk_test_arg_wins');
check(
    $apiKeyProp->getValue($argClient) === 'omsk_test_arg_wins',
    'constructor apiKey wins over env var'
);
putenv('OMNISOCIALS_API_KEY'); // clean up
if ($savedKey !== false) {
    putenv('OMNISOCIALS_API_KEY=' . $savedKey);
}

// Construct with a fake key and named args (per DESIGN.md client shape).
$client = new Client(apiKey: 'omsk_test_fake', maxRetries: 0);
check($client->getBaseUrl() === 'https://api.omnisocials.com/v1', 'default baseUrl');
check($client->getTimeout() === 30.0, 'default timeout is 30s');
check($client->getMaxRetries() === 0, 'maxRetries named arg respected');
check((new Client(apiKey: 'k'))->getMaxRetries() === 2, 'default maxRetries is 2');
check(
    (new Client(apiKey: 'k', baseUrl: 'https://example.com/v1/'))->getBaseUrl() === 'https://example.com/v1',
    'baseUrl trailing slash is trimmed'
);

echo "\n== Resource method surface (35 endpoints + health) ==\n";

$surface = [
    'posts' => [
        'class' => OmniSocials\Resource\Posts::class,
        'methods' => ['list', 'get', 'recentPlatform', 'create', 'createAndPublish', 'update', 'delete', 'publish'],
    ],
    'media' => [
        'class' => OmniSocials\Resource\Media::class,
        'methods' => ['list', 'get', 'upload', 'uploadFromUrl', 'uploadFromBase64', 'createUploadUrl', 'check', 'update', 'delete'],
    ],
    'folders' => [
        'class' => OmniSocials\Resource\Folders::class,
        'methods' => ['list', 'create', 'update', 'delete'],
    ],
    'accounts' => [
        'class' => OmniSocials\Resource\Accounts::class,
        'methods' => ['list', 'get'],
    ],
    'analytics' => [
        'class' => OmniSocials\Resource\Analytics::class,
        'methods' => ['post', 'posts', 'overview', 'accounts', 'bestTimes'],
    ],
    'locations' => [
        'class' => OmniSocials\Resource\Locations::class,
        'methods' => ['search', 'validate'],
    ],
    'webhooks' => [
        'class' => OmniSocials\Resource\Webhooks::class,
        'methods' => ['list', 'get', 'create', 'update', 'delete', 'rotateSecret'],
    ],
];

foreach ($surface as $property => $expected) {
    $resource = $client->{$property};
    check($resource instanceof $expected['class'], "client->{$property} is {$expected['class']}");
    $reflection = new ReflectionClass($resource);
    foreach ($expected['methods'] as $method) {
        check(
            $reflection->hasMethod($method) && $reflection->getMethod($method)->isPublic(),
            "client->{$property}->{$method}() exists and is public"
        );
    }
}

$clientReflection = new ReflectionClass($client);
check(
    $clientReflection->hasMethod('health') && $clientReflection->getMethod('health')->isPublic(),
    'client->health() exists and is public'
);

echo "\n== Exception hierarchy ==\n";

$rateLimit = new RateLimitException('slow down', 429, 'rate_limit_exceeded', ['error' => []], 45);
check($rateLimit instanceof ApiException, 'RateLimitException extends ApiException');
check($rateLimit instanceof OmniSocialsException, 'RateLimitException extends OmniSocialsException');
check($rateLimit->getRetryAfter() === 45, 'RateLimitException::getRetryAfter() returns 45');
check($rateLimit->getStatus() === 429, 'RateLimitException::getStatus() returns 429');
check($rateLimit->getErrorCode() === 'rate_limit_exceeded', 'RateLimitException::getErrorCode()');

foreach (
    [
        AuthenticationException::class,
        PermissionDeniedException::class,
        NotFoundException::class,
        ValidationException::class,
        ServerException::class,
    ] as $class
) {
    $e = new $class('boom', 400);
    check($e instanceof ApiException && $e instanceof OmniSocialsException, "{$class} extends ApiException");
}

$conn = new ApiConnectionException('network down');
check($conn instanceof OmniSocialsException && !($conn instanceof ApiException), 'ApiConnectionException is not an ApiException');
$whv = new WebhookVerificationException('bad signature');
check($whv instanceof OmniSocialsException && !($whv instanceof ApiException), 'WebhookVerificationException is not an ApiException');

// Status -> subclass mapping used by the request loop.
check(ApiException::create(400, 'm') instanceof ValidationException, 'create(400) -> ValidationException');
check(ApiException::create(422, 'm') instanceof ValidationException, 'create(422) -> ValidationException');
check(ApiException::create(401, 'm') instanceof AuthenticationException, 'create(401) -> AuthenticationException');
check(ApiException::create(403, 'm') instanceof PermissionDeniedException, 'create(403) -> PermissionDeniedException');
check(ApiException::create(404, 'm') instanceof NotFoundException, 'create(404) -> NotFoundException');
$mapped429 = ApiException::create(429, 'm', null, null, 12);
check($mapped429 instanceof RateLimitException && $mapped429->getRetryAfter() === 12, 'create(429) -> RateLimitException with retryAfter');
check(ApiException::create(500, 'm') instanceof ServerException, 'create(500) -> ServerException');
check(ApiException::create(503, 'm') instanceof ServerException, 'create(503) -> ServerException');
check(get_class(ApiException::create(409, 'm')) === ApiException::class, 'create(409) -> plain ApiException');

echo "\n== Webhook signature verification round-trip ==\n";

// Sign exactly like backend/services/webhooks/webhookDispatcher.js:
// signed value "{ts}.{rawBody}", HMAC-SHA256 hex, header "t=<ts>,v1=<hex>".
$secret = 'whsec_smoke_test_secret';
$event = [
    'id' => '3f1c9a2e-0000-4000-8000-000000000000',
    'type' => 'post.published',
    'created_at' => gmdate('c'),
    'data' => [
        'post_id' => '12345',
        'workspace_id' => 42,
        'status' => 'posted',
        'scheduled_at' => null,
        'targets' => [
            ['platform' => 'instagram', 'status' => 'success', 'native_post_id' => '1789'],
        ],
    ],
];
$rawBody = json_encode($event, JSON_UNESCAPED_SLASHES);
$timestamp = time();

$sign = static function (int $ts, string $body) use ($secret): string {
    return 't=' . $ts . ',v1=' . hash_hmac('sha256', $ts . '.' . $body, $secret);
};

// 1. Valid signature passes and returns the parsed event array.
$verified = Webhooks::verifySignature($rawBody, $sign($timestamp, $rawBody), $secret);
check($verified === $event, 'valid signature returns the parsed event array');
check($verified['data']['targets'][0]['platform'] === 'instagram', 'nested event data survives the round-trip');

// 2. Tampered body fails.
$tampered = str_replace('post.published', 'post.failed', $rawBody);
checkThrows(
    static fn () => Webhooks::verifySignature($tampered, $sign($timestamp, $rawBody), $secret),
    WebhookVerificationException::class,
    'tampered body is rejected'
);

// 3. Stale timestamp fails (signature itself is valid)...
$staleTs = $timestamp - 3600; // 1 hour old; default tolerance is 300s
checkThrows(
    static fn () => Webhooks::verifySignature($rawBody, $sign($staleTs, $rawBody), $secret),
    WebhookVerificationException::class,
    'stale timestamp is rejected'
);
// ...but passes when the tolerance is widened.
$staleOk = Webhooks::verifySignature($rawBody, $sign($staleTs, $rawBody), $secret, 7200);
check($staleOk['type'] === 'post.published', 'stale timestamp passes with a wide tolerance');

// 4. Wrong secret fails.
checkThrows(
    static fn () => Webhooks::verifySignature($rawBody, $sign($timestamp, $rawBody), 'whsec_wrong'),
    WebhookVerificationException::class,
    'wrong secret is rejected'
);

// 5. Malformed headers fail.
foreach (['not-a-signature', 't=abc,v1=deadbeef', 'v1=deadbeef', 't=' . $timestamp, ''] as $malformed) {
    checkThrows(
        static fn () => Webhooks::verifySignature($rawBody, $malformed, $secret),
        WebhookVerificationException::class,
        "malformed header is rejected ('{$malformed}')"
    );
}

// 6. Non-JSON payload with a valid signature fails.
checkThrows(
    static fn () => Webhooks::verifySignature('not json', $sign($timestamp, 'not json'), $secret),
    WebhookVerificationException::class,
    'non-JSON payload is rejected'
);

echo "\n";
if ($failures > 0) {
    echo "SMOKE FAILED: {$failures} check(s) failed.\n";
    exit(1);
}
echo "All smoke tests passed.\n";
echo "- Client constructs with named args, env fallback via getenv, missing key throws AuthenticationException\n";
echo "- 7 resources expose all DESIGN.md inventory methods (35 endpoints) plus health()\n";
echo "- Exception hierarchy and status mapping correct (RateLimitException::getRetryAfter works)\n";
echo "- Webhook signature: accepts valid, rejects tampered/stale/wrong-secret/malformed/non-JSON\n";
