# OmniSocials PHP SDK

The official PHP client for the [OmniSocials API](https://docs.omnisocials.com). Schedule and publish posts to Instagram, Facebook, LinkedIn, YouTube, TikTok, X, Pinterest, Bluesky, Threads, Mastodon, and Google Business from one API.

- No Composer dependencies, built on ext-curl and ext-json (PHP >= 8.1)
- Stripe-style resource objects with array params
- Automatic retries with exponential backoff, configurable timeouts
- Rich exception classes and a webhook signature verification helper
- PSR-4 autoloading, PSR-12 code style

## Installation

```bash
composer require omnisocials/omnisocials-php
```

## Quickstart

```php
use OmniSocials\Client;

$client = new Client(); // reads OMNISOCIALS_API_KEY from env
$post = $client->posts->create([
    'content' => 'Hello from the SDK',
    'channels' => ['instagram', 'linkedin'],
    'scheduled_at' => '2026-08-01T09:00:00Z',
]);
```

## Authentication

Create an API key in the OmniSocials app under **Settings -> API Keys**. Keys look like `omsk_live_...` (or `omsk_test_...`).

The client reads `OMNISOCIALS_API_KEY` from the environment, or you can pass it explicitly:

```php
$client = new Client(apiKey: 'omsk_live_...');
```

Constructing a client without a key throws an `OmniSocials\Exception\AuthenticationException` right away.

## Configuration

```php
$client = new Client(
    apiKey: 'omsk_live_...',
    baseUrl: 'https://api.omnisocials.com/v1', // default
    timeout: 30.0,   // per-request timeout in seconds (default 30)
    maxRetries: 2,   // automatic retries on 429 / 5xx / network errors (default 2)
);
```

Retries use exponential backoff (0.5s, 1s, 2s, ...) with jitter and honor the `Retry-After` header. Other 4xx responses are never retried.

## Rate limits

The API allows **100 requests per minute** per API key. When you exceed it, the SDK retries automatically (respecting `Retry-After`); if retries are exhausted it throws a `RateLimitException` whose `getRetryAfter()` method returns the seconds to wait.

## Return values

Methods return the parsed response body as-is, decoded to associative arrays: single items come back as `['data' => [...]]`, lists as `['data' => [...], 'pagination' => [...]]`, and some responses carry extra sibling keys (media uploads include `compatibility`, PDF uploads include `slides` and `media_ids`). Endpoints that respond `204 No Content` (deletes) return `null`.

## Posts

### Schedule a post

```php
$response = $client->posts->create([
    'content' => 'New drop this Friday',
    'channels' => ['instagram', 'facebook', 'linkedin'],
    'scheduled_at' => '2026-08-01T09:00:00Z',
    'media_urls' => ['https://example.com/teaser.jpg'],
]);
echo $response['data']['id'] . ' ' . $response['data']['status'];
```

Omit `scheduled_at` to create a draft. Use `content` as an array for per-platform captions:

```php
$client->posts->create([
    'content' => [
        'default' => 'New drop this Friday',
        'x' => 'New drop this Friday. RT to spread the word',
    ],
    'channels' => ['instagram', 'x'],
    'scheduled_at' => '2026-08-01T09:00:00Z',
]);
```

### Publish immediately

```php
$client->posts->createAndPublish([
    'content' => 'Going live right now',
    'channels' => ['x', 'bluesky'],
]);
```

### Per-media alt text

Every `media_urls` / `media_ids` entry accepts either a plain string or an array with an `alt` accessibility description (max 1500 chars). Alt text is delivered to Mastodon (media description), Bluesky (embed alt), X (photos and GIFs), and Pinterest (pin alt text). Strings and arrays can be mixed, and the same shape works in per-platform maps and `thread_parts` media.

```php
$client->posts->create([
    'content' => 'Sunrise over the harbor',
    'channels' => ['mastodon', 'bluesky'],
    'scheduled_at' => '2026-08-01T09:00:00Z',
    'media_urls' => [
        [
            'url' => 'https://example.com/harbor.jpg',
            'alt' => 'A small sailboat crossing a calm harbor at sunrise, sky in deep orange',
        ],
    ],
]);
```

### Post with platform-specific options

```php
$client->posts->create([
    'content' => 'Behind the scenes of our summer shoot',
    'channels' => ['instagram', 'youtube', 'x'],
    'scheduled_at' => '2026-08-01T09:00:00Z',
    'media_urls' => ['https://example.com/bts.mp4'],
    'instagram' => ['share_to_feed' => true],
    'youtube' => ['title' => 'Summer shoot BTS', 'privacy' => 'public'],
    'x' => ['reply_settings' => 'following', 'made_with_ai' => false],
]);
```

### X thread

Provide 2 to 25 `thread_parts` to publish a chained thread instead of a single tweet. Each part is capped at 280 characters and can carry its own media (`media_ids` / `media_urls`). The same `thread_parts` shape works for `bluesky` (300 chars per part) and `mastodon` (500 chars per part).

```php
$client->posts->create([
    'content' => 'How we grew to 10k followers in 90 days',
    'channels' => ['x'],
    'scheduled_at' => '2026-08-01T09:00:00Z',
    'x' => [
        'thread_parts' => [
            ['text' => 'How we grew to 10k followers in 90 days. A thread:'],
            ['text' => '1. We posted every single day, even when it felt pointless.'],
            ['text' => '2. We replied to every comment within an hour.'],
            ['text' => '3. Full breakdown on our blog. Link in bio.'],
        ],
    ],
]);
```

On update, pass `'thread_parts' => null` to clear thread mode (revert to a single post); leave the key out to keep the existing thread untouched.

### List, get, update, publish, delete

```php
$page = $client->posts->list(['status' => 'scheduled', 'limit' => 50]);
$posts = $page['data'];

$one = $client->posts->get($posts[0]['id']);
$client->posts->update($one['data']['id'], ['scheduled_at' => '2026-08-02T10:00:00Z']);
$client->posts->publish($one['data']['id']); // publish a draft/scheduled post now
$client->posts->delete($one['data']['id']);  // returns null (204)
```

### Recent platform posts

Fetch recent posts live from the connected platform APIs, including content published outside OmniSocials. Useful for brand-new workspaces where `list()` is empty. Requires the `analytics:read` scope.

```php
$recent = $client->posts->recentPlatform(['limit' => 10, 'platforms' => ['instagram', 'x']]);
```

## Media

### Upload from a URL (recommended, up to 1GB)

```php
$upload = $client->media->uploadFromUrl([
    'url' => 'https://example.com/launch-video.mp4',
    'name' => 'launch-video-v2',
    'folder' => 'Campaigns',
]);
echo $upload['data']['id'];
print_r($upload['compatibility']);
```

Videos over 100MB are processed in the background and come back with status `"processing"`. Every upload response includes a `compatibility` block listing connected platforms that would reject the file.

### Upload a local file (multipart)

`file` is either a filesystem path or the raw file contents as a string:

```php
// From a path
$client->media->upload(['file' => './photos/product.jpg', 'name' => 'product-hero']);

// Or from raw bytes (pass a filename so the API can detect the type)
$bytes = file_get_contents('./photos/product.jpg');
$client->media->upload(['file' => $bytes, 'filename' => 'product.jpg']);
```

Direct multipart uploads are capped at 100MB by the CDN; use `uploadFromUrl()` or the presigned flow below for bigger files.

### Upload from base64

```php
$client->media->uploadFromBase64([
    'data' => $base64String, // no data URI prefix
    'mime_type' => 'image/png',
    'filename' => 'chart.png',
]);
```

### PDF carousels

Uploading a PDF rasterizes it into one image slide per page (max 20). The response carries `slides` and `media_ids` alongside `data` (the first slide). Pass ALL of `media_ids`, in order, to `posts->create()` to post the deck as a carousel (a native swipeable document on LinkedIn, an image carousel elsewhere).

```php
$pdf = $client->media->uploadFromUrl(['url' => 'https://example.com/deck.pdf']);
$client->posts->create([
    'content' => 'Our Q3 strategy deck',
    'channels' => ['linkedin'],
    'media_ids' => $pdf['media_ids'],
    'scheduled_at' => '2026-08-01T09:00:00Z',
]);
```

### Presigned uploads for large files (up to 1GB)

`createUploadUrl()` mints a one-time upload URL. POST the file to it as multipart form data (field name `file`) within `expires_in_seconds` (600s); the second request needs no auth headers because the single-use token is in the URL. The response of that second request is the created media item (or `media_ids` for a PDF).

```php
$presigned = $client->media->createUploadUrl();

$ch = curl_init($presigned['upload_url']);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => ['file' => new CURLFile('./big-video.mp4')],
]);
$uploaded = json_decode(curl_exec($ch), true);
curl_close($ch);
echo $uploaded['data']['id'];
```

### Preflight compatibility check

Check a file against the workspace's connected platforms before uploading. Provide one of `url`, `media_id`, or `size_bytes` + `mime`.

```php
$client->media->check(['url' => 'https://example.com/huge.mov']);
$client->media->check(['size_bytes' => 300000000, 'mime' => 'video/quicktime']);
```

### List, get, rename, move, delete

```php
$items = $client->media->list(['search' => 'hero', 'limit' => 20]);
$first = $items['data'][0];

$client->media->update($first['id'], ['name' => 'hero-v2', 'folder_id' => '12']);
$client->media->get($first['id']);
$client->media->delete($first['id']); // 409 media_in_use if attached to a scheduled post
```

## Folders

```php
$folders = $client->folders->list(); // flat; build the tree via parent_id
$folder = $client->folders->create(['name' => 'Campaigns']);
$client->folders->update($folder['data']['id'], ['name' => 'Campaigns 2026']);
$client->folders->delete($folder['data']['id']); // files move to root, subfolders move up
```

## Hashtag Sets

Save reusable hashtag groups and apply them to posts at create time. Uses the `posts:read` / `posts:write` scopes.

```php
$set = $client->hashtagSets->create([
    'name' => 'Launch',
    'hashtags' => ['saas', 'buildinpublic', 'startup'], // or one string: '#saas #buildinpublic #startup'
]);
echo $set['data']['preview']; // "#saas #buildinpublic #startup"

$client->hashtagSets->list();
$client->hashtagSets->get($set['data']['id']);
$client->hashtagSets->update($set['data']['id'], ['hashtags' => ['saas', 'founder']]); // replaces the full list
$client->hashtagSets->delete($set['data']['id']); // returns null (204)
```

Apply a set when creating a post with `hashtag_set` (the set name, case-insensitive) or `hashtag_set_id`. The set is applied once at create time and tags already in the caption are skipped. `hashtag_placement` is `'caption_append'` (default) or `'first_comment'`, and `hashtag_platforms` restricts the hashtags to a subset of the post's channels. Instagram's 30-hashtag cap returns error code `hashtag_limit_exceeded`.

```php
$client->posts->create([
    'content' => 'Launch day!',
    'channels' => ['instagram', 'x'],
    'scheduled_at' => '2026-08-01T09:00:00Z',
    'hashtag_set' => 'Launch',
    'hashtag_placement' => 'first_comment',
    'hashtag_platforms' => ['instagram'],
]);
```

## Accounts

```php
$accounts = $client->accounts->list();
foreach ($accounts['data'] as $account) {
    echo "{$account['platform']} {$account['username']} {$account['status']}\n";
    if (!empty($account['needs_reconnect'])) {
        echo "{$account['platform']} needs a reconnect: {$account['reauth_reason']}\n";
    }
}
$ig = $client->accounts->get($accounts['data'][0]['id']);
```

## Analytics

```php
// One post's latest per-platform metrics
$stats = $client->analytics->post('post_id');
print_r($stats['data']['platforms']['instagram']['metrics'] ?? null);

// Batch: up to 100 posts in one call
$batch = $client->analytics->posts(['id1', 'id2', 'id3']);

// Workspace-wide overview
$overview = $client->analytics->overview(['period' => '30d']);
echo $overview['data']['total_impressions'] . ' ' . $overview['data']['total_engagements'];

// Account-level stats (followers etc)
$accountStats = $client->analytics->accounts(['platform' => 'instagram']);
```

### Best times to post

```php
$best = $client->analytics->bestTimes([
    'platform' => 'instagram',
    'timezone' => 'Europe/Amsterdam',
]);
```

## Locations (Instagram place tagging)

```php
$results = $client->locations->search('Griffith Observatory');
$place = $results['data'][0];

$check = $client->locations->validate($place['id']);
if (!empty($check['valid'])) {
    $client->posts->create([
        'content' => 'Golden hour at the observatory',
        'channels' => ['instagram'],
        'media_urls' => ['https://example.com/observatory.jpg'],
        'location_id' => $place['id'],
        'scheduled_at' => '2026-08-01T18:30:00Z',
    ]);
}
```

## Social Inbox

DMs, comments, and mentions from Instagram, Facebook, and LinkedIn in one place. The list endpoints are **cursor-paginated** (`{ next_cursor, has_more, limit }`), unlike the offset-paginated lists elsewhere.

```php
// List conversations (all filters optional)
$conversations = $client->inbox->listConversations([
    'platform' => 'instagram', // instagram | facebook | linkedin
    'type' => 'dm',            // dm | comment | mention
    'unread' => true,
    'limit' => 25,             // 1-100
]);

foreach ($conversations['data'] as $conversation) {
    // Full message history for one conversation.
    // LinkedIn ids contain ":" and "()" - they are URL-encoded for you.
    $messages = $client->inbox->getMessages($conversation['id']);

    // Mark the whole conversation as read.
    $client->inbox->markRead($conversation['id']);

    // Reply (text required; attachment optional).
    $client->inbox->reply($conversation['id'], [
        'text' => 'Thanks for reaching out!',
        'attachment_url' => 'https://example.com/reply.jpg',
        'attachment_type' => 'image', // image | video | audio | file
    ]);
}

// Page through with the cursor.
$cursor = $conversations['pagination']['next_cursor'] ?? null;
while ($cursor !== null) {
    $page = $client->inbox->listConversations(['cursor' => $cursor]);
    // ... handle $page['data'] ...
    $cursor = ($page['pagination']['has_more'] ?? false)
        ? $page['pagination']['next_cursor']
        : null;
}
```

## Webhooks

### Manage endpoints

```php
$webhook = $client->webhooks->create([
    'url' => 'https://example.com/omnisocials/webhook',
    'events' => ['post.published', 'post.failed'],
]);
echo $webhook['data']['secret']; // save it, it is only shown once

$client->webhooks->list();
$client->webhooks->get($webhook['data']['id']);
$client->webhooks->update($webhook['data']['id'], ['is_active' => false]);
$rotated = $client->webhooks->rotateSecret($webhook['data']['id']);
echo $rotated['data']['secret']; // the old secret stops working
$client->webhooks->delete($webhook['data']['id']);
```

### Verify deliveries (plain PHP endpoint)

Every delivery is signed with your webhook secret. The `X-OmniSocials-Signature` header has the form `t=<unix>,v1=<hex>` where the hex value is an HMAC-SHA256 of `"{timestamp}.{rawBody}"`. Always verify against the RAW request body:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use OmniSocials\Webhooks;
use OmniSocials\Exception\WebhookVerificationException;

$payload = file_get_contents('php://input'); // the raw body, untouched
$signature = $_SERVER['HTTP_X_OMNISOCIALS_SIGNATURE'] ?? '';

try {
    $event = Webhooks::verifySignature(
        $payload,
        $signature,
        getenv('OMNISOCIALS_WEBHOOK_SECRET'),
        300 // tolerance in seconds (default)
    );
} catch (WebhookVerificationException $e) {
    http_response_code(400);
    exit;
}

switch ($event['type']) {
    case 'post.published':
        error_log('Published: ' . $event['data']['post_id']);
        break;
    case 'post.failed':
        error_log('Failed: ' . $event['data']['post_id']);
        break;
}

http_response_code(200);
```

### Verify deliveries (Laravel route)

Use `$request->getContent()` for the raw body, and exclude the route from CSRF verification (webhooks carry no CSRF token):

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use OmniSocials\Webhooks;
use OmniSocials\Exception\WebhookVerificationException;

Route::post('/omnisocials/webhook', function (Request $request) {
    try {
        $event = Webhooks::verifySignature(
            $request->getContent(),
            $request->header('X-OmniSocials-Signature', ''),
            config('services.omnisocials.webhook_secret')
        );
    } catch (WebhookVerificationException $e) {
        return response()->json(['error' => 'invalid signature'], 400);
    }

    if ($event['type'] === 'post.published') {
        logger()->info('Post published', $event['data']);
    }

    return response()->noContent();
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
```

`Webhooks::verifySignature()` uses a constant-time comparison (`hash_equals`), rejects timestamps older than the tolerance (replay protection), throws `WebhookVerificationException` on any failure, and returns the parsed event array on success.

## Health

```php
$health = $client->health(); // ['status' => 'ok', 'version' => '1.0.0', 'timestamp' => '...']
```

## Error handling

All exceptions thrown by the SDK extend `OmniSocials\Exception\OmniSocialsException`. Non-2xx API responses throw an `ApiException` subclass exposing `getStatus()`, `getErrorCode()`, `getMessage()`, and the parsed `getBody()`:

| Class | Status | Typical API codes |
|---|---|---|
| `ValidationException` | 400 / 422 | `validation_error`, `platform_not_connected`, `invalid_file_type` |
| `AuthenticationException` | 401 | `unauthorized`, `invalid_api_key` |
| `PermissionDeniedException` | 403 | `forbidden`, `insufficient_scope` |
| `NotFoundException` | 404 | `not_found` |
| `RateLimitException` | 429 | `rate_limit_exceeded` (exposes `getRetryAfter()` seconds) |
| `ServerException` | >= 500 | `internal_error` |
| `ApiConnectionException` | n/a | network failure or timeout |
| `WebhookVerificationException` | n/a | invalid webhook signature |

```php
use OmniSocials\Exception\ApiConnectionException;
use OmniSocials\Exception\ApiException;
use OmniSocials\Exception\RateLimitException;
use OmniSocials\Exception\ValidationException;

try {
    $client->posts->create(['content' => 'Hi', 'channels' => ['instagram']]);
} catch (RateLimitException $e) {
    echo 'Rate limited, retry in ' . $e->getRetryAfter() . "s\n";
} catch (ValidationException $e) {
    echo "Bad request ({$e->getErrorCode()}): {$e->getMessage()}\n";
    print_r($e->getBody());
} catch (ApiConnectionException $e) {
    echo 'Network problem: ' . $e->getMessage() . "\n";
} catch (ApiException $e) {
    echo "API error {$e->getStatus()} ({$e->getErrorCode()}): {$e->getMessage()}\n";
}
```

## API scopes

Each API key carries scopes: `posts:read`, `posts:write`, `media:write`, `accounts:read`, `analytics:read`, `webhooks:manage`. A call with a missing scope throws `PermissionDeniedException` with code `insufficient_scope`.

## Documentation

Full API reference and guides: [https://docs.omnisocials.com](https://docs.omnisocials.com)

## License

MIT
