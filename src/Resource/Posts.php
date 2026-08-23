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
     * `type`, `link_url`, `location_id`, `linkedin_poll`, `collaborators`,
     * `user_tags`, and per-platform option arrays (`instagram`, `facebook`,
     * `linkedin`, `linkedin_page`, `youtube`, `tiktok`, `pinterest`, `x`,
     * `bluesky`, `mastodon`, `threads`, `google_business`). For X / Bluesky /
     * Mastodon / Threads, pass `['thread_parts' => [['text' => '...'], ...]]`
     * (2 to 25 parts) to publish a chained thread. Threads: 500 characters
     * per part, up to 10 media per part; parts after the first publish as
     * replies to the previous part, and the Threads caption is taken from
     * part 1.
     *
     * `linkedin_poll` (nullable) publishes a non-sponsored LinkedIn poll
     * instead of a normal post - mutually exclusive with media / link-share
     * on that post: `['question' => '...', 'options' => ['...', '...'],
     * 'duration' => 'ONE_DAY']`. `question` is max 140 chars, `options`
     * takes 2 to 4 entries of max 30 chars each, and `duration` is one of
     * `ONE_DAY`, `THREE_DAYS`, `SEVEN_DAYS`, `FOURTEEN_DAYS`.
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
     * Separately, every scheduled X link post reserves its cost up front.
     * A non-draft `create()` (also `update()` and `publish()`) throws an
     * `ApiException` with status 402 and error code `x_credits_insufficient`
     * (`getBody()['error']['details']` carries `credits_required`,
     * `credits_balance`, `credits_reserved`) when reserving this post's cost
     * would push the company's total reserved credits past its balance.
     * Drafts are never gated, and posts publishing before 2026-08-14 are
     * never gated.
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
     * `create()` for the `warnings` array and the `x_credits_insufficient`
     * schedule-time gate on X link posts.
     *
     * @param array<string, mixed> $params
     */
    public function createAndPublish(array $params): mixed
    {
        return $this->client->post('/posts/create-and-publish', $params);
    }

    /**
     * `PATCH /posts/:id` - update a draft or scheduled post. For X / Bluesky /
     * Mastodon / Threads, `['thread_parts' => null]` clears thread mode
     * (revert to a single post); omitting the key leaves the existing thread
     * untouched.
     * Likewise, `['linkedin_poll' => null]` clears an existing poll and
     * reverts the post to a normal post; omitting the key leaves it
     * untouched.
     *
     * Scheduling or editing an X link post can throw the same `402
     * x_credits_insufficient` gate described on `create()`.
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
     * Can throw the `402 x_credits_insufficient` gate described on
     * `create()` if publishing this X link post would push reserved
     * credits past the company's balance.
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
