<?php

/**
 * Smoke test for `mention_author` on inbox->reply of the OmniSocials PHP SDK,
 * against a loopback stub server (`php -S` on 127.0.0.1). No composer
 * install needed. Run with:
 *
 *   php scripts/inbox-mention-smoke.php
 *
 * Verifies that inbox->reply sends `mention_author` as given (and nothing
 * extra without it) and returns the `mention` object of the answer.
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

echo "== inbox->reply mention_author against a loopback stub server ==\n";

$stubDir = sys_get_temp_dir() . '/omnisocials-php-mention-' . bin2hex(random_bytes(4));
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
$answer = ['data' => ['id' => '104970664', 'text' => 'Jamie Rivera Thanks!']];
if (str_contains($body, '"mention_author":true')) {
    $answer['mention'] = [
        'status' => 'tagged',
        'name' => 'Jamie Rivera',
        'message' => 'Facebook shows the name as a clickable tag.',
    ];
}
echo json_encode($answer);
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
    $tagged = $client->inbox->reply('fb_comment_1_2', [
        'text' => 'Thanks!',
        'message_id' => '104970331',
        'mention_author' => true,
    ]);
    $plain = $client->inbox->reply('fb_comment_1_2', ['text' => 'Thanks!']);
    $sent = $requests();

    check(
        $sent[0]['method'] === 'POST' && $sent[0]['uri'] === '/v1/inbox/conversations/fb_comment_1_2/reply'
            && $sent[0]['body'] === '{"text":"Thanks!","message_id":"104970331","mention_author":true}',
        'inbox->reply sends mention_author'
    );
    check(
        ($tagged['mention']['status'] ?? null) === 'tagged' && ($tagged['mention']['name'] ?? null) === 'Jamie Rivera',
        'inbox->reply returns mention'
    );
    check($sent[1]['body'] === '{"text":"Thanks!"}', 'inbox->reply sends no mention_author when not given');
    check(!isset($plain['mention']), 'no mention without mention_author');
} finally {
    proc_terminate($server);
    proc_close($server);
    array_map('unlink', glob($stubDir . '/*') ?: []);
    rmdir($stubDir);
}

echo "\n";
if ($failures > 0) {
    echo "INBOX MENTION SMOKE FAILED: {$failures} check(s) failed.\n";
    exit(1);
}
echo "All inbox mention smoke tests passed.\n";
