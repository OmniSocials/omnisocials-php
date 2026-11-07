<?php

/**
 * Smoke test for the approve and reject calls of the OmniSocials PHP SDK,
 * against a loopback stub server (`php -S` on 127.0.0.1). No composer
 * install needed. Run with:
 *
 *   php scripts/approvals-smoke.php
 *
 * Verifies that posts->approve sends `step_order` only when given, that
 * posts->reject sends `comment` and `step_order` only when given (and the
 * old `reject($id, $comment)` call still works), and that a 409
 * `step_changed` keeps `current_step_order` in the body.
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

echo "== Approve and reject calls against a loopback stub server ==\n";

// The router logs every request; a step_order of 1 is a review that moved on.
$stubDir = sys_get_temp_dir() . '/omnisocials-php-approvals-' . bin2hex(random_bytes(4));
mkdir($stubDir);
file_put_contents($stubDir . '/router.php', <<<'PHP'
<?php
$body = file_get_contents('php://input');
file_put_contents(__DIR__ . '/requests.jsonl', json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'body' => $body,
]) . "\n", FILE_APPEND);
header('Content-Type: application/json');
$sent = json_decode($body ?: 'null', true);
if (is_array($sent) && ($sent['step_order'] ?? null) === 1) {
    http_response_code(409);
    echo json_encode(['error' => ['code' => 'step_changed', 'message' => 'The review is on step 2 now.', 'current_step_order' => 2]]);
} else {
    echo json_encode(['data' => ['id' => '1024', 'status' => 'in_approval', 'approval_status' => 'pending']]);
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
    $client->posts->approve('1024');
    $client->posts->approve('1024', 2);
    $client->posts->approve('1024', stepOrder: 3);
    $client->posts->reject('1024');
    $client->posts->reject('1024', 'Wrong CTA link');
    $client->posts->reject('1024', 'Wrong CTA link', 2);
    $client->posts->reject('1024', stepOrder: 3);
    $sent = $requests();

    check(
        $sent[0]['method'] === 'POST' && $sent[0]['uri'] === '/v1/posts/1024/approve' && $sent[0]['body'] === '',
        'posts->approve without a step sends no body'
    );
    check($sent[1]['body'] === '{"step_order":2}', 'posts->approve sends step_order');
    check($sent[2]['body'] === '{"step_order":3}', 'posts->approve takes stepOrder as a named argument');
    check(
        $sent[3]['uri'] === '/v1/posts/1024/reject' && $sent[3]['body'] === '',
        'posts->reject without arguments sends no body'
    );
    check($sent[4]['body'] === '{"comment":"Wrong CTA link"}', 'posts->reject($id, $comment) still works');
    check(
        $sent[5]['body'] === '{"comment":"Wrong CTA link","step_order":2}',
        'posts->reject sends comment and step_order'
    );
    check($sent[6]['body'] === '{"step_order":3}', 'posts->reject sends step_order alone');

    try {
        $client->posts->approve('1024', 1);
        check(false, 'a review that moved on throws');
    } catch (ApiException $e) {
        check($e->getStatus() === 409 && $e->getErrorCode() === 'step_changed', 'step_changed is a 409 ApiException');
        $body = $e->getBody();
        check(
            is_array($body) && ($body['error']['current_step_order'] ?? null) === 2,
            'step_changed keeps current_step_order in the body'
        );
    }
} finally {
    proc_terminate($server);
    proc_close($server);
    array_map('unlink', glob($stubDir . '/*') ?: []);
    rmdir($stubDir);
}

echo "\n";
if ($failures > 0) {
    echo "APPROVALS SMOKE FAILED: {$failures} check(s) failed.\n";
    exit(1);
}
echo "All approval smoke tests passed.\n";
