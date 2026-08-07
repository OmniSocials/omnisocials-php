<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

class Posts extends AbstractResource
{
    /**
     * `GET /posts` - list posts in the workspace (newest first).
     *
     * @param array{status?: string, limit?: int, offset?: int} $params
     */
    public function list(array $params = []): mixed
    {
        return $this->client->get('/posts', [
            'status' => $params['status'] ?? null,
            'limit' => $params['limit'] ?? null,
            'offset' => $params['offset'] ?? null,
        ]);
    }

    /**
     * `GET /posts/:id` - fetch a single post.
     */
    public function get(string $id): mixed
    {
        return $this->client->get('/posts/' . $this->encodePathSegment($id));
    }

    /**
     * `GET /posts/recent-platform` - recent posts fetched live from the
     * connected platform APIs (including content published outside
     * OmniSocials). The fallback for brand-new workspaces where `list()` is
     * empty. Requires the `analytics:read` scope.
     *
     * @param array{limit?: int, platforms?: string|string[]} $params
     */
    public function recentPlatform(array $params = []): mixed
    {
        $platforms = $params['platforms'] ?? null;
        if (is_array($platforms)) {
            $platforms = implode(',', $platforms);
        }

        return $this->client->get('/posts/recent-platform', [
            'limit' => $params['limit'] ?? null,
            'platforms' => $platforms,
        ]);
    }

    /**
     * `POST /posts/create` - create a draft or scheduled post.
     *
     * Common params: `content` (string, or array keyed by platform plus
     * "default"), `channels`, `scheduled_at`, `media_ids`, `media_urls`,
     * `type`, `link_url`, `location_id`, `collaborators`, `user_tags`, and
     * per-platform option arrays (`instagram`, `facebook`, `linkedin`,
     * `linkedin_page`, `youtube`, `tiktok`, `pinterest`, `x`, `bluesky`,
     * `mastodon`, `google_business`). For X / Bluesky / Mastodon, pass
     * `['thread_parts' => [['text' => '...'], ...]]` (2 to 25 parts) to
     * publish a chained thread.
     *
     * Each `media_urls` / `media_ids` entry is a plain string, or an array
     * with an `alt` accessibility description (max 1500 chars):
     * `['url' => 'https://...', 'alt' => '...']` for media_urls,
     * `['id' => '...', 'alt' => '...']` for media_ids. Alt text is delivered
     * to Mastodon (media description), Bluesky (embed alt), X (photos/GIFs),
     * Pinterest (pin alt text), Instagram (images), and LinkedIn (images);
     * the same entry shape works inside `thread_parts` media.
     *
     * When the post targets X and its text (or any thread part) contains a
     * URL, the response includes a top-level `warnings` array (sibling of
     * `data`) with a `x_url_post_credits` entry carrying `credits_required`
     * and `credits_balance`: X's link-post fee is passed through as prepaid
     * credits, debited at publish time (from 2026-08-14). Credits are
     * managed in the dashboard, not the API.
     *
     * @param array<string, mixed> $params
     */
    public function create(array $params): mixed
    {
        return $this->client->post('/posts/create', $params);
    }

    /**
     * `POST /posts/create-and-publish` - create a post and publish it
     * immediately. Same params as `create()` minus `scheduled_at`; see
     * `create()` for the `warnings` array on X link posts.
     *
     * @param array<string, mixed> $params
     */
    public function createAndPublish(array $params): mixed
    {
        return $this->client->post('/posts/create-and-publish', $params);
    }

    /**
     * `PATCH /posts/:id` - update a draft or scheduled post. For X / Bluesky /
     * Mastodon, `['thread_parts' => null]` clears thread mode (revert to a
     * single post); omitting the key leaves the existing thread untouched.
     *
     * @param array<string, mixed> $params
     */
    public function update(string $id, array $params): mixed
    {
        return $this->client->patch('/posts/' . $this->encodePathSegment($id), $params);
    }

    /**
     * `DELETE /posts/:id` - delete a post. Returns null (204).
     */
    public function delete(string $id): mixed
    {
        return $this->client->delete('/posts/' . $this->encodePathSegment($id));
    }

    /**
     * `POST /posts/:id/publish` - publish a draft or scheduled post now.
     */
    public function publish(string $id): mixed
    {
        return $this->client->post('/posts/' . $this->encodePathSegment($id) . '/publish');
    }

    /**
     * `POST /posts/:id/retry` - retry the failed platforms of a `failed` or
     * `warning` (partially failed) post, on the same post. Only the platforms
     * that failed are re-published; platforms that already succeeded are
     * never posted again. Asynchronous: a 200 means the retry is queued -
     * poll `get()` for the outcome. Max 3 retries per platform.
     */
    public function retry(string $id): mixed
    {
        return $this->client->post('/posts/' . $this->encodePathSegment($id) . '/retry');
    }
}
