<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

use OmniSocials\Client;

class Analytics extends AbstractResource
{
    /**
     * `GET /analytics/posts/:id` - latest per-platform metrics for one post.
     */
    public function post(string $postId): mixed
    {
        return $this->client->get('/analytics/posts/' . $this->encodePathSegment($postId));
    }

    /**
     * `POST /analytics/posts/:id/refresh` - "Update now": read the X numbers
     * of one published post now, outside its check schedule. X bills every
     * read, so it costs half a credit per tweet (each part of a thread
     * counts, plus half a credit for a video or GIF), and nothing when the
     * post was already read today (UTC). OmniSocials Connect accounts pay on
     * the partner's own X app. At most 5 reads of a post a UTC day. Needs
     * `analytics:read`. Returns `{ data: { post_id, tweets: [{ id, metrics,
     * read_at }], credits_used, read_at } }`. Because it can cost credits it
     * is retried only after a 429, never after a 5xx, a timeout or a lost
     * connection. Errors: `402 insufficient_credits`, `404 not_found`, `409
     * refresh_not_available` (no published X tweet), `429 refresh_limit`,
     * `502 refresh_failed` (nothing charged).
     */
    public function refreshPost(int|string $postId): mixed
    {
        return $this->client->post(
            '/analytics/posts/' . $this->encodePathSegment((string) $postId) . '/refresh',
            null,
            Client::RETRY_RATE_LIMIT
        );
    }

    /**
     * `GET /analytics/posts?ids=a,b,c` - batch metrics for up to 100 posts in
     * one call instead of one request per post.
     *
     * @param string[] $postIds
     */
    public function posts(array $postIds): mixed
    {
        return $this->client->get('/analytics/posts', ['ids' => implode(',', $postIds)]);
    }

    /**
     * `GET /analytics/overview` - workspace-wide totals for a period. `tag`
     * (a tag id or name, comma separated for any of several) limits the post
     * numbers to the posts with that tag; account numbers stay those of the
     * whole account.
     *
     * @param array{period?: string, start_date?: string, end_date?: string, tag?: string|int} $params
     */
    public function overview(array $params = []): mixed
    {
        return $this->client->get('/analytics/overview', [
            'period' => $params['period'] ?? null,
            'start_date' => $params['start_date'] ?? null,
            'end_date' => $params['end_date'] ?? null,
            'tag' => $params['tag'] ?? null,
        ]);
    }

    /**
     * `GET /analytics/tags` - how each post tag did against your normal
     * (default: the last 90 days). Returns `{ data: { period, platform,
     * min_posts, tags, untagged, normals } }`: `tags` lists the comparable
     * tags first, best first, each with `tag`, `posts`, `measured`,
     * `median_impressions`, `engagement_rate` (percent), `vs_normal` (a
     * fraction, 0.38 = 38% above normal), `best_channel` and `enough_posts`.
     * Dates: "YYYY-MM-DD", "YYYY-MM" or ISO 8601. `min_posts` (default 5,
     * 1 to 50) = measured posts a tag needs before `enough_posts` is true.
     *
     * @param array{start_date?: string, end_date?: string, platform?: string, min_posts?: int} $params
     */
    public function tags(array $params = []): mixed
    {
        return $this->client->get('/analytics/tags', [
            'start_date' => $params['start_date'] ?? null,
            'end_date' => $params['end_date'] ?? null,
            'platform' => $params['platform'] ?? null,
            'min_posts' => $params['min_posts'] ?? null,
        ]);
    }

    /**
     * `GET /analytics/accounts` - account-level stats (followers etc).
     *
     * @param array{platform?: string, date?: string} $params
     */
    public function accounts(array $params = []): mixed
    {
        return $this->client->get('/analytics/accounts', [
            'platform' => $params['platform'] ?? null,
            'date' => $params['date'] ?? null,
        ]);
    }

    /**
     * `GET /analytics/best-times` - recommended posting time slots for a
     * platform, based on when the account's audience engages.
     *
     * @param array{platform: string, timezone?: string} $params
     */
    public function bestTimes(array $params): mixed
    {
        return $this->client->get('/analytics/best-times', [
            'platform' => $params['platform'] ?? null,
            'timezone' => $params['timezone'] ?? null,
        ]);
    }
}
