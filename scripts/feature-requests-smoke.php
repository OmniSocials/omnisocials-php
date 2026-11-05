<?php

/**
 * Smoke test for the limits and feature request calls of the OmniSocials
 * PHP SDK, against a loopback stub server (`php -S` on 127.0.0.1). No
 * composer install needed. Run with:
 *
 *   php scripts/feature-requests-smoke.php
 *
 * Verifies the method, path, query and body of limits->list and
 * featureRequests->create/list/get, that create returns `outcome` next to
 * `data` (also on a 200 `already_requested`), and that a 409
 * `not_requestable` keeps the limit in the body.
 */

declare(strict_types=1);

error_reporting(E_ALL);

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
use OmniSocials\Exception\ApiException;

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

echo "== Limits and feature request calls against a loopback stub server ==\n";

// The router answers by path and logs every request. Loopback only.
$stubDir = sys_get_temp_dir() . '/omnisocials-php-feature-requests-' . bin2hex(random_bytes(4));
mkdir($stubDir);
file_put_contents($stubDir . '/router.php', <<<'PHP'
<?php
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$body = file_get_contents('php://input');
file_put_contents(__DIR__ . '/requests.jsonl', json_encode([
    'method' => $method,
    'uri' => $_SERVER['REQUEST_URI'],
    'body' => $body,
]) . "\n", FILE_APPEND);
$limit = [
    'id' => 'tiktok.delete', 'kind' => 'platform', 'kind_label' => 'The platform does not allow it',
    'area' => 'posts', 'platforms' => ['tiktok'], 'title' => 'Delete a TikTok video',
    'says' => "TikTok's API cannot delete a video.", 'instead' => 'Delete the video in the TikTok app.',
    'requestable' => false,
];
$request = [
    'id' => 412, 'title' => 'Post to a topic of a Telegram group',
    'summary' => 'Post our daily update to the Announcements topic of our Telegram group.',
    'status' => 'open', 'team_answer' => null, 'team_replies' => [], 'votes_count' => 1,
    'limit_id' => 'telegram.forum_topics', 'platform' => 'telegram', 'area' => 'posts', 'source' => 'api',
    'created_at' => '2026-11-04T10:00:00.000Z', 'updated_at' => '2026-11-04T10:00:00.000Z', 'completed_at' => null,
];
header('Content-Type: application/json');
$sent = json_decode($body ?: 'null', true);
if ($path === '/v1/limits') {
    echo json_encode(['data' => [$limit], 'kinds' => ['platform' => 'The platform does not allow it']]);
} elseif ($method === 'POST' && ($sent['limit_id'] ?? null) === 'tiktok.delete') {
    http_response_code(409);
    echo json_encode(['error' => [
        'code' => 'not_requestable',
        'message' => "TikTok's API cannot delete a video. A request cannot change this, so nothing was sent.",
        'limit' => $limit,
    ]]);
} elseif ($method === 'POST' && str_starts_with($sent['summary'] ?? '', 'Again')) {
    echo json_encode(['outcome' => 'already_requested', 'data' => $request]);
} elseif ($method === 'POST') {
    http_response_code(201);
    echo json_encode(['outcome' => 'voted', 'data' => $request]);
} elseif ($path === '/v1/feature-requests') {
    echo json_encode(['data' => [$request]]);
} else {
    echo json_encode(['data' => $request]);
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

$requests = static function () use ($stubDir): array {
    $lines = file($stubDir . '/requests.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    file_put_contents($stubDir . '/requests.jsonl', '');
    return array_map(static fn (string $line): array => json_decode($line, true), $lines);
};
file_put_contents($stubDir . '/requests.jsonl', '');

$client = new Client(apiKey: 'omsk_test_x', baseUrl: "http://127.0.0.1:{$port}/v1", maxRetries: 0);

try {
    $limits = $client->limits->list(['platform' => 'tiktok', 'area' => 'posts']);
    $client->limits->list();
    $voted = $client->featureRequests->create([
        'summary' => 'Post our daily update to the Announcements topic of our Telegram group.',
        'platform' => 'telegram',
        'limit_id' => 'telegram.forum_topics',
    ]);
    $again = $client->featureRequests->create(['summary' => 'Again: post to a Telegram topic, please.']);
    $mine = $client->featureRequests->list();
    $one = $client->featureRequests->get(412);
    $sent = $requests();

    check($sent[0]['method'] === 'GET' && $sent[0]['uri'] === '/v1/limits?platform=tiktok&area=posts', 'limits->list GETs /limits with platform and area');
    check($limits['data'][0]['requestable'] === false && $limits['kinds']['platform'] === 'The platform does not allow it', 'limits->list returns data and kinds');
    check($sent[1]['uri'] === '/v1/limits', 'limits->list without params sends no query');
    check(
        $sent[2]['method'] === 'POST' && $sent[2]['uri'] === '/v1/feature-requests'
            && $sent[2]['body'] === '{"summary":"Post our daily update to the Announcements topic of our Telegram group.","platform":"telegram","limit_id":"telegram.forum_topics"}',
        'featureRequests->create POSTs the given keys (never source)'
    );
    check($voted['outcome'] === 'voted' && $voted['data']['id'] === 412, 'featureRequests->create returns outcome and data');
    check($again['outcome'] === 'already_requested', 'a 200 already_requested keeps the outcome');
    check($sent[4]['method'] === 'GET' && $sent[4]['uri'] === '/v1/feature-requests', 'featureRequests->list GETs /feature-requests');
    check($mine['data'][0]['status'] === 'open', 'featureRequests->list returns the requests');
    check($sent[5]['uri'] === '/v1/feature-requests/412', 'featureRequests->get GETs /feature-requests/412 (int id)');
    check($one['data']['limit_id'] === 'telegram.forum_topics', 'featureRequests->get returns the request');

    try {
        $client->featureRequests->create(['summary' => 'Delete my TikTok videos from OmniSocials.', 'limit_id' => 'tiktok.delete']);
        check(false, 'a not_requestable limit throws');
    } catch (ApiException $e) {
        check($e->getStatus() === 409 && $e->getErrorCode() === 'not_requestable', 'not_requestable is a 409 ApiException');
        $body = $e->getBody();
        check(
            is_array($body) && ($body['error']['limit']['instead'] ?? null) === 'Delete the video in the TikTok app.',
            'not_requestable keeps the limit in the body'
        );
    }
    check(count($requests()) === 1, 'a 409 is sent once');
} finally {
    proc_terminate($server);
    proc_close($server);
    array_map('unlink', glob($stubDir . '/*') ?: []);
    rmdir($stubDir);
}

echo "\n";
if ($failures > 0) {
    echo "FEATURE REQUESTS SMOKE FAILED: {$failures} check(s) failed.\n";
    exit(1);
}
echo "All limits and feature request smoke tests passed.\n";
