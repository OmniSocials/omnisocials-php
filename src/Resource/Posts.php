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
     * `bluesky`, `mastodon`, `threads`, `google_business`, `telegram`,
     * `discord`). For X / Bluesky /
     * Mastodon / Threads, pass `['thread_parts' => [['text' => '...'], ...]]`
     * (2 to 25 parts) to publish a chained thread. Threads: 500 characters
     * per part, up to 10 media per part; parts after the first publish as
     * replies to the previous part, and the Threads caption is taken from
     * part 1.
     *
     * Telegram posts take a `telegram` array (the connected channel or
     * group, posted by the OmniSocials bot): `silent`, `link_preview`,
     * `pin`, `protect_content`, `spoiler`, `caption_above_media` (booleans),
     * up to 3 `buttons` (`['text' => ..., 'url' => ...]`, not on an album or
     * a poll), a native `poll` (`['options' => [...], 'open_period' => 3600,
     * 'multiple' => true]`: 2 to 12 options of up to 100 characters; the post
     * text is the question, max 300 characters, no media) and
     * `first_comment` (a reply under the post). Text up to 4,096 characters;
     * with media, text over 1,024 characters is sent as a reply under the
     * media. No Stories. On update every key merges into the stored options.
     * Once sent, the Post's `telegram` block carries `result` (message ids
     * and links).
     *
     * Discord posts take a `discord` array (one Discord channel, posted
     * through its incoming webhook with the webhook's name and picture):
     * `silent`, `link_preview` (default on), `allow_mentions` (default off:
     * @everyone, @here, role and user mentions show as text and ping
     * nobody), `spoiler` (booleans), `thread_name` (the post title in a
     * forum or media channel, 1 to 100 characters; on a text channel the
     * post goes out without it), up to 5 `buttons` (`['text' => ..., 'url' => ...]`: 1 to 80
     * characters, https:// or http://, one row, not with a poll) and a
     * native `poll` (`['options' => [...], 'duration_hours' => 24,
     * 'multiple' => true]`: 2 to 10 options of up to 55 characters, 1 to 768
     * hours, default 24; the post text is the question, max 300 characters,
     * no media and no buttons). Text up to 2,000 characters; up to 10
     * images, GIFs and videos per message, a video or GIF over 20 MB is sent
     * as a link in the text. No Stories. Once sent, the Post's `discord`
     * block carries `result` (message id and link).
     *
     * Threads posts can carry a location tag: `'threads' =>
     * ['location_id' => '...']` (an id from
     * `$client->locations->search($q, ['platform' => 'threads'])`), or
     * `'location' => ['id' => '...', 'name' => '...']` to store display
     * fields along with the id (`location_id` wins when both are given). On
     * a multi-post thread the tag is applied to part 1, and the Post's
     * `threads` block echoes a `location` object when set. Threads location
     * tagging is currently rolling out; until Meta approves the permissions
     * it is disabled on production and create/update/publish return a 400
     * (also a 400 `validation_error` asking you to reconnect Threads when
     * the connection lacks the `threads_location_tagging` permission).
     *
     * Threads posts can carry a topic: `'threads' => ['topic_tag' => 'Build
     * in public']` (1 to 50 characters, no period and no ampersand; a topic
     * can hold spaces). On a multi-post thread it goes on part 1; on update
     * `'topic_tag' => null` removes it.
     *
     * Pinterest posts can tag products: `'pinterest' => ['board_id' => '...',
     * 'product_tags' => [...]]` with up to 24 product Pins of the connected
     * Pinterest account, each as a Pin id string (see
     * `$client->pinterest->listProducts()`) or a Pin link. Products of other
     * merchants cannot be tagged. The tags are added right after the Pin is
     * published; a product Pinterest refuses never fails the post, and the
     * Post's `pinterest` block then carries `product_tags_result`
     * (`requested`, `tagged`, `skipped`, `error`). More than 24 entries or
     * an invalid entry throws a 400 `ValidationException`.
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
     * `approval_workflow_id` (a workflow id from `$client->approvalWorkflows->list()`)
     * routes the post through a saved approval workflow: it is created as
     * `in_approval` (`approval_status: "pending"`) instead of `scheduled`, the
     * approvers are notified, and it publishes at `scheduled_at` once the last
     * step approves. Requires `scheduled_at`; not allowed with `publish_now`.
     * Errors: `404 workflow_not_found`, `400 validation_error`.
     *
     * `draft => true` with `scheduled_at` stores a planned draft: the post is
     * `draft` and keeps that time, shows on the dashboard calendar at that
     * slot with a dashed outline, and the scheduler never publishes it. A
     * person schedules it from the composer, or you do with `update()` and
     * `draft => false`. Use it when an agent proposes a slot that a human
     * should confirm. Not allowed with `publish_now` or `approval_workflow_id`
     * (`400 validation_error`). Without `scheduled_at` it changes nothing.
     *
     * `media_fit` is the image fit per channel for feed images whose ratio
     * is outside what a channel shows uncropped: an array of platform id
     * (`'instagram'`, `'pinterest'`, ...) to `'auto'`, `'crop'` or `'keep'`.
     * `auto` (default): Instagram crops a tall or wide image to 4:5 / 1.91:1
     * around the subject (Instagram cuts it to that window anyway and Meta
     * cannot boost a feed post outside it); Pinterest keeps a Pin taller
     * than 2:3 and reports it. `crop` / `keep` force it per channel. Every
     * decision comes back in the same `warnings` array with code
     * `image_fit` (`action` is `cropped`, `kept` or `crop_failed`, with
     * `platform`, `media_url`, `ratio`, `target` and `crop_url`).
     *
     * `comment_dm_automation` binds a comment-to-DM automation to the post
     * (Instagram or Facebook must be in `channels`): a keyword in a comment
     * is answered with a DM, once per person, with an optional public reply
     * under the comment. Shape: `['trigger' => ['keywords' => ['guide'],
     * 'match' => 'word'], 'actions' => ['dm' => ['text' => '...'],
     * 'public_reply' => ['enabled' => true, 'variations' => [...]]],
     * 'limits' => ['max_sends' => 500], 'platforms' => ['instagram',
     * 'facebook']]` (see `$client->automations->create()` for the fields).
     * `platforms` makes one automation per listed channel; each must be a
     * channel of the post. Without it: Instagram when the post goes there,
     * else Facebook. Needs the `automations:write` scope on the key
     * (`403 insufficient_scope`, checked before anything else); a bad array
     * is a `400 validation_error` whose message starts with
     * `comment_dm_automation: `. The response then carries
     * `data.comment_dm_automation` (the first automation, with `post` null,
     * plus `platforms` and `automations`, one `id` / `platform` / `status`
     * per channel); each starts listening once the post is live on its
     * channel.
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
     * `create()` for the `warnings` array (X link posts, and the `image_fit`
     * entries steered by `media_fit`), the `x_credits_insufficient`
     * schedule-time gate on X link posts and `comment_dm_automation`.
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
     * untouched. A Threads location tag clears the same way:
     * `'threads' => ['location_id' => null]` (or `'location' => null`)
     * removes the tag, while omitting the key leaves it untouched.
     * Likewise, `['linkedin_poll' => null]` clears an existing poll and
     * reverts the post to a normal post; omitting the key leaves it
     * untouched.
     *
     * `pinterest` replaces the stored Pinterest options wholesale, so leave
     * `product_tags` out (or send `[]`) to remove the product tags.
     *
     * `draft => false` without `scheduled_at` schedules a planned draft at the
     * `scheduled_at` it already carries (`400 validation_error` when it has
     * none or it is in the past). `draft => true` without `scheduled_at`
     * unschedules a scheduled post: it keeps its time and stays on the
     * calendar as a dashed draft, nothing publishes; a post waiting for
     * approval leaves its workflow. `draft => true` with `scheduled_at` moves
     * the time and keeps (or makes) the post a draft.
     *
     * `media_fit` (same shape as on `create()`) alone re-decides the stored
     * images: `'keep'` gives the original back, `'crop'` crops an image that
     * was kept. Like `create()`, the response can carry a top-level
     * `warnings` array with `image_fit` entries when the update changes the
     * images or `media_fit`.
     *
     * Scheduling or editing an X link post can throw the same `402
     * x_credits_insufficient` gate described on `create()`.
     *
     * `comment_dm_automation` (same shape as on `create()`) as an array
     * replaces the post's comment-to-DM automations (a channel no longer in
     * `platforms` loses its automation), `['comment_dm_automation' => null]`
     * removes them all, and omitting the key leaves them untouched. Needs
     * the `automations:write` scope.
     *
     * `discord` merges into the stored Discord options: a key set to `null`
     * removes that option (`'discord' => ['poll' => null]`),
     * `['discord' => null]` removes every option, and omitting the key
     * leaves them untouched.
     *
     * @param array<string, mixed> $params
     */
    public function update(string $id, array $params): mixed
    {
        return $this->client->patch('/posts/' . $this->encodePathSegment($id), $params);
    }

    /**
     * `DELETE /posts/:id` - remove a post from OmniSocials (the live post stays on the platform). Returns null (204).
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

    /**
     * `POST /posts/:id/approve` - approve the current step of a post's
     * approval workflow, on behalf of the user who owns this API key. That
     * user must be a listed approver for the workflow's CURRENT step -
     * steps approve in order, so an approver on a later step gets a 403
     * `forbidden` error until earlier steps clear. Only works on a post
     * with `approval_status: "pending"`. If this is the last step, the
     * post finalizes immediately (`scheduled` or `posting`); otherwise it
     * stays `in_approval` and the next step's approvers are notified.
     */
    public function approve(string $id): mixed
    {
        return $this->client->post('/posts/' . $this->encodePathSegment($id) . '/approve');
    }

    /**
     * `POST /posts/:id/reject` - reject a post's approval workflow, on
     * behalf of the user who owns this API key. Same approver requirement
     * as `approve()`. Unlike approval, a rejection stops the WHOLE workflow
     * immediately (not just the current step) - the post's status becomes
     * `rejected`. `$comment` is optional and, when given, is shown to the
     * requester and other approvers in the post's review thread.
     */
    public function reject(string $id, ?string $comment = null): mixed
    {
        $body = $comment !== null ? ['comment' => $comment] : null;
        return $this->client->post('/posts/' . $this->encodePathSegment($id) . '/reject', $body);
    }

    /**
     * `GET /posts/:id/approval` - the approval review of a post: every step
     * with its approvers and their decisions, the rejection with its
     * reason, and the comment thread. Use it when `approval_status` is
     * `rejected` to learn who rejected the post and why, or while it is
     * `pending` to see who the post waits for.
     *
     * `data` carries `post_id`, `status` (`none`, `pending`, `approved`,
     * `rejected`), `workflow`, `requested_by`, `requested_at`,
     * `current_step`, `steps`, `rejection` and `comments` (oldest first).
     * A post without an approval workflow returns `status: "none"` with
     * the object fields null and empty `steps` and `comments`. Read-only;
     * requires the `posts:read` scope.
     */
    public function getApproval(string $id): mixed
    {
        return $this->client->get('/posts/' . $this->encodePathSegment($id) . '/approval');
    }
}
