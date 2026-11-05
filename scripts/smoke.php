<?php

/**
 * Smoke test for the OmniSocials PHP SDK. No composer install needed:
 * a tiny PSR-4 autoloader is registered inline. Run with:
 *
 *   php scripts/smoke.php
 *
 * Verifies: client construction, env fallback, resource method surface
 * (every endpoint in the DESIGN.md inventory), the exception hierarchy,
 * the ads calls against a loopback stub server (`php -S` on 127.0.0.1:
 * method, path and body, a call that can start ad spend is sent once after
 * a 502 or a timeout, a read is retried), and a webhook signature round-trip
 * (same scheme as backend webhookDispatcher.js: HMAC-SHA256 hex over
 * "{ts}.{body}", header "t=<ts>,v1=<hex>").
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

echo "\n== Resource method surface (every endpoint + health) ==\n";

$surface = [
    'posts' => [
        'class' => OmniSocials\Resource\Posts::class,
        'methods' => ['list', 'get', 'recentPlatform', 'create', 'createAndPublish', 'update', 'delete', 'publish', 'approve', 'reject', 'getApproval'],
    ],
    'media' => [
        'class' => OmniSocials\Resource\Media::class,
        'methods' => ['list', 'get', 'upload', 'uploadFromUrl', 'uploadFromBase64', 'createUploadUrl', 'check', 'update', 'delete'],
    ],
    'folders' => [
        'class' => OmniSocials\Resource\Folders::class,
        'methods' => ['list', 'create', 'update', 'delete'],
    ],
    'hashtagSets' => [
        'class' => OmniSocials\Resource\HashtagSets::class,
        'methods' => ['list', 'get', 'create', 'update', 'delete'],
    ],
    'tags' => [
        'class' => OmniSocials\Resource\Tags::class,
        'methods' => ['list', 'create', 'update', 'delete', 'apply'],
    ],
    'accounts' => [
        'class' => OmniSocials\Resource\Accounts::class,
        'methods' => ['list', 'get'],
    ],
    'analytics' => [
        'class' => OmniSocials\Resource\Analytics::class,
        'methods' => ['post', 'posts', 'overview', 'accounts', 'bestTimes', 'tags'],
    ],
    'locations' => [
        'class' => OmniSocials\Resource\Locations::class,
        'methods' => ['search', 'validate'],
    ],
    'pinterest' => [
        'class' => OmniSocials\Resource\Pinterest::class,
        'methods' => ['listProducts', 'validateProduct'],
    ],
    'automations' => [
        'class' => OmniSocials\Resource\Automations::class,
        'methods' => ['list', 'get', 'create', 'update', 'delete', 'listEvents'],
    ],
    'ads' => [
        'class' => OmniSocials\Resource\Ads::class,
        'methods' => ['listAccounts', 'capabilities', 'list', 'get', 'boost', 'boostMulti', 'create', 'update', 'activate', 'activateMulti', 'pause', 'resume', 'delete', 'overview', 'suggestions', 'researchKeywords', 'keywordMetrics'],
    ],
    'webhooks' => [
        'class' => OmniSocials\Resource\Webhooks::class,
        'methods' => ['list', 'get', 'create', 'update', 'delete', 'rotateSecret'],
    ],
    'limits' => [
        'class' => OmniSocials\Resource\Limits::class,
        'methods' => ['list'],
    ],
    'featureRequests' => [
        'class' => OmniSocials\Resource\FeatureRequests::class,
        'methods' => ['create', 'list', 'get'],
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

check($client->ads->drafts instanceof OmniSocials\Resource\AdDrafts, 'client->ads->drafts is ' . OmniSocials\Resource\AdDrafts::class);
$draftsReflection = new ReflectionClass($client->ads->drafts);
foreach (['defaults', 'create', 'get', 'update', 'delete', 'attachAccount', 'launch'] as $method) {
    check(
        $draftsReflection->hasMethod($method) && $draftsReflection->getMethod($method)->isPublic(),
        "client->ads->drafts->{$method}() exists and is public"
    );
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

echo "\n== Ads calls against a loopback stub server ==\n";

// A `php -S` router that answers from a script of statuses (the last one
// repeats; "slow" answers 200 after 1s) and logs every request. Loopback only.
$stubDir = sys_get_temp_dir() . '/omnisocials-php-smoke-' . bin2hex(random_bytes(4));
mkdir($stubDir);
file_put_contents($stubDir . '/router.php', <<<'PHP'
<?php
$answers = json_decode((string) file_get_contents(__DIR__ . '/answers.json'), true);
$answer = count($answers) > 1 ? array_shift($answers) : $answers[0];
file_put_contents(__DIR__ . '/answers.json', json_encode($answers));
file_put_contents(__DIR__ . '/requests.jsonl', json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'body' => file_get_contents('php://input'),
]) . "\n", FILE_APPEND);
header('Content-Type: application/json');
if ($answer === 'slow') {
    sleep(1);
    $answer = 200;
}
http_response_code($answer);
if ($answer >= 400) {
    header('Retry-After: 0'); // keeps the retried reads fast
    echo '{"error":{"code":"bad_gateway","message":"upstream"}}';
} else {
    echo '{"data":{"ok":true}}';
}
PHP);

$probe = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
fclose($probe);
$server = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:{$port}", $stubDir . '/router.php'],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $serverPipes
);
$ready = false;
for ($i = 0; $i < 50 && !$ready; $i++) {
    $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
    if ($conn !== false) {
        fclose($conn);
        $ready = true;
    } else {
        usleep(100_000);
    }
}
check($ready, "stub server listens on 127.0.0.1:{$port}");

/**
 * Reset the stub with a new script of answers; returns a reader for the
 * requests it logs.
 */
$stub = static function (array $answers) use ($stubDir): callable {
    file_put_contents($stubDir . '/answers.json', json_encode($answers));
    file_put_contents($stubDir . '/requests.jsonl', '');
    return static function () use ($stubDir): array {
        $lines = file($stubDir . '/requests.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        return array_map(static fn (string $line): array => json_decode($line, true), $lines);
    };
};
$adsClient = new Client(apiKey: 'omsk_test_x', baseUrl: "http://127.0.0.1:{$port}/v1", maxRetries: 2);
$draft = [
    'name' => 'Autumn sale',
    'objective' => 'traffic',
    'platforms' => ['meta', ['platform' => 'linkedin', 'budget_amount' => 20]],
    'budget' => ['amount' => 10, 'currency' => 'EUR'],
    'schedule' => ['duration_days' => null],
    'targeting' => ['countries' => ['NL', 'BE'], 'automatic_audience' => true],
    'ads' => [[
        'media' => [['url' => 'https://example.com/sale.jpg', 'type' => 'image']],
        'primary_text' => '30% off everything this week',
        'link' => 'https://example.com/sale',
    ]],
];

$requests = $stub([201]);
$adsClient->ads->drafts->create($draft);
$sent = $requests();
check(
    count($sent) === 1 && $sent[0]['method'] === 'POST' && $sent[0]['uri'] === '/v1/ads/drafts',
    'ads->drafts->create POSTs /ads/drafts'
);
check(json_decode($sent[0]['body'], true) === $draft, 'ads->drafts->create sends the draft (nested null kept)');

$requests = $stub([200]);
$adsClient->ads->drafts->update('g 1/x', $draft);
$sent = $requests();
check(
    $sent[0]['method'] === 'PUT' && $sent[0]['uri'] === '/v1/ads/drafts/g%201%2Fx',
    'ads->drafts->update PUTs /ads/drafts/:group_id with the id encoded'
);
check(json_decode($sent[0]['body'], true) === $draft, 'ads->drafts->update sends the full body');

$requests = $stub([200]);
$adsClient->ads->drafts->launch('g-1', ['confirm_budget_amount' => 10]);
$adsClient->ads->drafts->launch('g-1', ['confirm_budget_amounts' => [7 => 10, 8 => 20]]);
$sent = $requests();
check(
    $sent[0]['method'] === 'POST' && $sent[0]['uri'] === '/v1/ads/drafts/g-1/launch'
        && $sent[0]['body'] === '{"confirm_budget_amount":10}',
    'ads->drafts->launch POSTs the confirmed amount'
);
check($sent[1]['body'] === '{"confirm_budget_amounts":{"7":10,"8":20}}', 'ads->drafts->launch sends per campaign amounts as an object');

$requests = $stub([200]);
$adsClient->ads->activate(7, 25);
$adsClient->ads->activateMulti([7, 8], 25);
$adsClient->ads->activate('51', 25);
$sent = $requests();
check($sent[2]['uri'] === '/v1/ads/campaigns/51/activate', 'ads->activate takes the id as a string (the API sends ids as strings)');
check(
    $sent[0]['method'] === 'POST' && $sent[0]['uri'] === '/v1/ads/campaigns/7/activate'
        && $sent[0]['body'] === '{"confirm_budget_amount":25}',
    'ads->activate POSTs confirm_budget_amount'
);
check(
    $sent[1]['uri'] === '/v1/ads/campaigns/activate-multi'
        && $sent[1]['body'] === '{"campaign_ids":[7,8],"confirm_budget_amount":25}',
    'ads->activateMulti sends campaign_ids and confirm_budget_amount'
);

$moneyCalls = [
    'drafts->launch' => static fn () => $adsClient->ads->drafts->launch('g-1', ['confirm_budget_amount' => 10]),
    'activate' => static fn () => $adsClient->ads->activate(7, 25),
    'activateMulti' => static fn () => $adsClient->ads->activateMulti([7, 8], 25),
    'resume' => static fn () => $adsClient->ads->resume(7),
    'update' => static fn () => $adsClient->ads->update(7, ['budget' => ['amount' => 50, 'currency' => 'EUR'], 'confirm_budget_amount' => 50]),
];
foreach ($moneyCalls as $name => $call) {
    $requests = $stub([502]);
    checkThrows($call, ServerException::class, "ads->{$name} throws ServerException on a 502");
    check(count($requests()) === 1, "ads->{$name} after a 502 is sent exactly once");
}

$requests = $stub([429, 200]);
$adsClient->ads->activate(7, 25);
check(count($requests()) === 2, 'ads->activate after a 429 is retried');

$requests = $stub([502, 200]);
$capabilities = $adsClient->ads->capabilities();
check(
    count($requests()) === 2 && $capabilities === ['data' => ['ok' => true]],
    'ads->capabilities (a read) after a 502 is retried'
);

$requests = $stub(['slow', 200]);
$hasty = new Client(apiKey: 'omsk_test_x', baseUrl: "http://127.0.0.1:{$port}/v1", timeout: 0.3, maxRetries: 2);
checkThrows(
    static fn () => $hasty->ads->drafts->launch('g-1', ['confirm_budget_amount' => 10]),
    ApiConnectionException::class,
    'ads->drafts->launch throws ApiConnectionException on a timeout'
);
usleep(1_200_000); // let the slow answer finish before counting
check(count($requests()) === 1, 'ads->drafts->launch after a timeout is sent exactly once');

proc_terminate($server);
proc_close($server);
array_map('unlink', glob($stubDir . '/*') ?: []);
rmdir($stubDir);

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

// A post.rejected delivery keeps its `approval` object.
$rejected = [
    'id' => 'e7c9a1b2-3d4e-4f6a-8b8c-9d0e1f2a3b4c',
    'type' => 'post.rejected',
    'created_at' => gmdate('c'),
    'data' => [
        'post_id' => '123456',
        'workspace_id' => 789,
        'status' => 'rejected',
        'targets' => [],
        'approval' => ['status' => 'rejected', 'decided_by' => 'c4a09e1d', 'reason' => 'Wrong product photo'],
    ],
];
$rejectedBody = json_encode($rejected, JSON_UNESCAPED_SLASHES);
$rejectedEvent = Webhooks::verifySignature($rejectedBody, $sign($timestamp, $rejectedBody), $secret);
check(
    $rejectedEvent['type'] === 'post.rejected' && $rejectedEvent['data']['approval'] === $rejected['data']['approval'],
    'post.rejected event keeps its approval object'
);

// An ad.status_changed delivery carries the campaign, not a post.
$adEvent = [
    'id' => 'b1f2c3d4-5e6f-7a8b-9c0d-1e2f3a4b5c6d',
    'type' => 'ad.status_changed',
    'created_at' => '2026-07-13T10:00:05Z',
    'data' => [
        'campaign_id' => 42,
        'workspace_id' => '456',
        'name' => 'Boost: Summer launch post',
        'platform' => 'meta',
        'campaign_type' => 'boost',
        'status' => 'active',
        'previous_status' => 'pending_review',
        'external_campaign_id' => '23851234567890123',
        'budget' => ['type' => 'daily', 'amount' => 10, 'currency' => 'EUR'],
    ],
];
$adBody = json_encode($adEvent, JSON_UNESCAPED_SLASHES);
$verifiedAd = Webhooks::verifySignature($adBody, $sign($timestamp, $adBody), $secret);
check(
    $verifiedAd['type'] === 'ad.status_changed' && $verifiedAd['data'] === $adEvent['data'],
    'ad.status_changed event verifies and keeps its campaign data'
);

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
echo "- 11 resources expose all DESIGN.md inventory methods plus health(), and ads->drafts its 7\n";
echo "- Ads calls send the right method, path and body; spend calls are sent once after a 502 or a timeout\n";
echo "- Exception hierarchy and status mapping correct (RateLimitException::getRetryAfter works)\n";
echo "- Webhook signature: accepts valid, rejects tampered/stale/wrong-secret/malformed/non-JSON\n";
