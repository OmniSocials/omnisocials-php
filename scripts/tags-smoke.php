<?php

/**
 * Smoke test for the post tag calls of the OmniSocials PHP SDK, against a
 * loopback stub server (`php -S` on 127.0.0.1). No composer install needed.
 * Run with:
 *
 *   php scripts/tags-smoke.php
 *
 * Verifies the method, path, query and body of tags->list/create/update/
 * delete/apply, analytics->tags, the `tag` filter on posts->list and
 * analytics->overview, `tags` on posts->create/update (an empty list is sent
 * as `[]`), and that a 409 `tag_exists` keeps the existing tag in the body.
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

echo "== Post tag calls against a loopback stub server ==\n";

// The router answers by path and logs every request. Loopback only.
$stubDir = sys_get_temp_dir() . '/omnisocials-php-tags-' . bin2hex(random_bytes(4));
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
$tag = ['id' => 12, 'name' => 'Question', 'color' => 'blue', 'created_at' => '2026-11-02T10:00:00.000Z'];
header('Content-Type: application/json');
if ($method === 'POST' && $path === '/v1/tags' && str_contains($body, '"Taken"')) {
    http_response_code(409);
    echo json_encode(['error' => ['code' => 'tag_exists', 'message' => 'A tag with this name exists.', 'tag' => $tag]]);
} elseif ($path === '/v1/tags/apply') {
    echo json_encode(['data' => ['updated' => 1, 'posts' => [['id' => '1024', 'tags' => [$tag]]], 'not_found' => ['999']]]);
} elseif ($path === '/v1/analytics/tags') {
    $summary = ['posts' => 4, 'measured' => 4, 'median_impressions' => 900, 'engagement_rate' => 3.1, 'vs_normal' => -0.1, 'best_channel' => null, 'enough_posts' => false];
    echo json_encode(['data' => [
        'period' => ['start_date' => '2026-08-01T00:00:00.000Z', 'end_date' => '2026-10-31T23:59:59.999Z'],
        'platform' => 'tiktok',
        'min_posts' => 3,
        'tags' => [array_merge($summary, ['tag' => $tag, 'vs_normal' => 0.38, 'enough_posts' => true])],
        'untagged' => $summary,
        'normals' => ['tiktok' => 1100],
    ]]);
} elseif ($method === 'DELETE') {
    echo json_encode(['data' => ['tag' => $tag, 'posts_untagged' => 3]]);
} elseif ($method === 'GET' && $path === '/v1/tags') {
    echo json_encode(['data' => [$tag]]);
} else {
    http_response_code($method === 'POST' && $path === '/v1/tags' ? 201 : 200);
    echo json_encode(['data' => $tag]);
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
    $list = $client->tags->list();
    $created = $client->tags->create(['name' => 'Question', 'color' => 'blue']);
    $client->tags->update(12, ['name' => 'Questions']);
    $deleted = $client->tags->delete('12');
    $applied = $client->tags->apply(['post_ids' => ['1024', '999'], 'add' => ['Question', 7]]);
    $sent = $requests();

    check($sent[0]['method'] === 'GET' && $sent[0]['uri'] === '/v1/tags', 'tags->list GETs /tags');
    check($list['data'][0]['name'] === 'Question' && !isset($list['data'][0]['group']), 'tags->list returns flat tags');
    check(
        $sent[1]['method'] === 'POST' && $sent[1]['uri'] === '/v1/tags'
            && $sent[1]['body'] === '{"name":"Question","color":"blue"}',
        'tags->create POSTs /tags with name and color'
    );
    check($created['data']['id'] === 12, 'tags->create returns the tag');
    check(
        $sent[2]['method'] === 'PATCH' && $sent[2]['uri'] === '/v1/tags/12' && $sent[2]['body'] === '{"name":"Questions"}',
        'tags->update PATCHes /tags/12 (int id)'
    );
    check($sent[3]['method'] === 'DELETE' && $sent[3]['uri'] === '/v1/tags/12', 'tags->delete DELETEs /tags/12');
    check($deleted['data']['posts_untagged'] === 3, 'tags->delete returns posts_untagged');
    check(
        $sent[4]['method'] === 'POST' && $sent[4]['uri'] === '/v1/tags/apply'
            && $sent[4]['body'] === '{"post_ids":["1024","999"],"add":["Question",7]}',
        'tags->apply POSTs ids and names as given'
    );
    check(
        $applied['data']['updated'] === 1 && $applied['data']['not_found'] === ['999']
            && $applied['data']['posts'][0]['tags'][0]['color'] === 'blue',
        'tags->apply returns updated, posts and not_found'
    );

    $compare = $client->analytics->tags([
        'start_date' => '2026-08-01',
        'end_date' => '2026-10-31',
        'platform' => 'tiktok',
        'min_posts' => 3,
    ]);
    $client->analytics->tags();
    $client->posts->list(['tag' => 'Question,7']);
    $client->analytics->overview(['period' => '30d', 'tag' => 'Question']);
    $client->posts->create(['content' => 'Would you post this?', 'channels' => ['tiktok'], 'tags' => ['Question', 12]]);
    $client->posts->update('1', ['tags' => []]);
    $sent = $requests();

    check(
        $sent[0]['uri'] === '/v1/analytics/tags?start_date=2026-08-01&end_date=2026-10-31&platform=tiktok&min_posts=3',
        'analytics->tags sends the query'
    );
    check($sent[1]['uri'] === '/v1/analytics/tags', 'analytics->tags without params sends no query');
    check(
        !isset($compare['data']['groups']) && $compare['data']['tags'][0]['tag']['name'] === 'Question'
            && $compare['data']['tags'][0]['vs_normal'] === 0.38 && $compare['data']['untagged']['best_channel'] === null,
        'analytics->tags returns a flat tags list and untagged'
    );
    check($sent[2]['uri'] === '/v1/posts?tag=Question%2C7', 'posts->list sends the tag filter');
    check($sent[3]['uri'] === '/v1/analytics/overview?period=30d&tag=Question', 'analytics->overview sends the tag filter');
    check(
        json_decode($sent[4]['body'], true)['tags'] === ['Question', 12],
        'posts->create sends tags as ids or names'
    );
    check(
        $sent[5]['method'] === 'PATCH' && $sent[5]['body'] === '{"tags":[]}',
        'posts->update sends an empty tags list as []'
    );

    try {
        $client->tags->create(['name' => 'Taken']);
        check(false, 'tags->create of a taken name throws');
    } catch (ApiException $e) {
        check($e->getStatus() === 409 && $e->getErrorCode() === 'tag_exists', 'tag_exists is a 409 ApiException');
        $body = $e->getBody();
        check(is_array($body) && ($body['error']['tag']['id'] ?? null) === 12, 'tag_exists keeps the existing tag in the body');
    }
} finally {
    proc_terminate($server);
    proc_close($server);
    array_map('unlink', glob($stubDir . '/*') ?: []);
    rmdir($stubDir);
}

echo "\n";
if ($failures > 0) {
    echo "TAGS SMOKE FAILED: {$failures} check(s) failed.\n";
    exit(1);
}
echo "All tag smoke tests passed.\n";
