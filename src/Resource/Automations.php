<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

/**
 * Comment-to-DM automations. An automation answers a keyword in an Instagram
 * comment (or a DM, or a story reply) with a DM, once per person, with an
 * optional public reply under the comment. With `'platform' => 'facebook'`
 * it runs on a Facebook Page instead, and the DM lands in Messenger (a Page
 * has no story replies). With `'platform' => 'x'` it answers a keyword in a
 * DM to the X account (`dm` kind only, no post, no public reply) while X DMs
 * are on for the workspace; every X answer ends with "Reply STOP to stop
 * automatic messages." and a person who replies STOP gets no automatic DM
 * again. The scopes are opt-in on the API key: `automations:read` for list / get / listEvents, `automations:write`
 * for create / update / delete (and for `comment_dm_automation` on posts).
 */
class Automations extends AbstractResource
{
    /**
     * `GET /automations` - list the workspace's comment-to-DM automations,
     * optionally by `status` (`active`, `paused`, `completed`), by
     * `platform` (`instagram`, `facebook`, `x`) or by the OmniSocials `post_id`
     * they are bound to. Requires the `automations:read` scope (opt-in on
     * the API key).
     *
     * @param array{status?: string, platform?: string, post_id?: string} $params
     */
    public function list(array $params = []): mixed
    {
        return $this->client->get('/automations', [
            'status' => $params['status'] ?? null,
            'platform' => $params['platform'] ?? null,
            'post_id' => $params['post_id'] ?? null,
        ]);
    }

    /**
     * `GET /automations/:id` - fetch a single automation. Requires the
     * `automations:read` scope (opt-in on the API key).
     */
    public function get(string $id): mixed
    {
        return $this->client->get('/automations/' . $this->encodePathSegment($id));
    }

    /**
     * `POST /automations` - create a comment-to-DM automation. `platform`
     * is `instagram` (default) or `facebook`: on a Facebook Page a keyword
     * in a comment (or a Messenger message to the Page) is answered in
     * Messenger, and a Page has no story replies. `'platform' => 'x'`
     * answers a keyword in a DM to the X account: `trigger.kinds` must be
     * `['dm']` (X allows an automated DM only after the person wrote first),
     * with no post and no public reply; an active X automation needs X DMs
     * on for the workspace (`409 x_dms_off`; `'status' => 'paused'` always
     * saves), and every X answer ends with "Reply STOP to stop automatic
     * messages.". Bind it to one post with
     * `post_id` (an OmniSocials post that goes to the channel, scheduled or
     * live; one automation per post and platform), `instagram_media_id` (an
     * Instagram post published outside OmniSocials) or `facebook_post_id`
     * (a Page post published outside OmniSocials, as `{page-id}_{post-id}`
     * or the post id alone; implies `facebook`), at most one; a `trigger`
     * with `scope` `next_post` or `any` needs none.
     *
     * `actions` is required: `['dm' => ['text' => '...']]` (max 1000
     * characters) plus an optional `'public_reply' => ['enabled' => true,
     * 'variations' => [...]]` (comment kind only, up to 10 of 300
     * characters). `trigger` is `['kinds' => ['comment'], 'scope' =>
     * 'post', 'keywords' => [...], 'match' => 'word']`: `kinds` one or more
     * of `comment`, `dm`, `story_reply`; `keywords` up to 20 of 60
     * characters, required unless `match` is `any`; `match` is `word`,
     * `contains` or `any`. `limits` is `['max_sends' => 500, 'stop_at' =>
     * '2026-12-31T00:00:00Z']` (both optional). `status` is `active`
     * (default) or `paused`. Meta rules, the same on Instagram and
     * Facebook: one private reply per comment, within 7 days; OmniSocials
     * caps sends at 180 DMs an hour per account and platform, and on X at 15
     * per 15 minutes and 450 a day; an answer over a cap waits in a queue
     * (`queued`) for up to an hour.
     *
     * Requires the `automations:write` scope (opt-in on the API key).
     * Errors: `400 validation_error`, `400 post_not_instagram`,
     * `400 post_not_facebook`, `400 post_required`, `404 post_not_found`,
     * `404 instagram_media_not_found`, `404 facebook_post_not_found`,
     * `409 instagram_not_connected`, `409 instagram_needs_reauth`,
     * `409 facebook_not_connected`, `409 facebook_needs_reauth`,
     * `409 x_not_connected`, `409 x_dms_off`, `409 automation_exists` (the
     * error body also carries `automation_id`).
     *
     * @param array<string, mixed> $params
     */
    public function create(array $params): mixed
    {
        return $this->client->post('/automations', $params);
    }

    /**
     * `PATCH /automations/:id` - partial update. Each section (`trigger`,
     * `actions`, `limits`) is merged into the stored one; keys you omit keep
     * their value: new `trigger.keywords` keep the kinds, the scope and the
     * match; a new `actions.dm.text` keeps the public reply;
     * `limits.stop_at` keeps `max_sends`. `'public_reply' => ['enabled' =>
     * false]` or `['variations' => []]` turns the public reply off;
     * `'limits' => ['max_sends' => null]` removes that limit.
     * `'status' => 'paused'` pauses and `'active'` resumes; another
     * `post_id` / `instagram_media_id` / `facebook_post_id` moves the
     * automation to that post. The platform cannot change: a `platform`
     * other than the automation's is a `400 platform_immutable`.
     * `'status' => 'active'` on an X automation needs X DMs on for the
     * workspace (`409 x_dms_off`). A `completed` automation starts again
     * when its limits or trigger change.
     * Requires the `automations:write` scope. `400 no_fields` when the body
     * is empty.
     *
     * @param array<string, mixed> $params
     */
    public function update(string $id, array $params): mixed
    {
        return $this->client->patch('/automations/' . $this->encodePathSegment($id), $params);
    }

    /**
     * `DELETE /automations/:id` - delete an automation. Returns null (204).
     * Requires the `automations:write` scope.
     */
    public function delete(string $id): mixed
    {
        return $this->client->delete('/automations/' . $this->encodePathSegment($id));
    }

    /**
     * `GET /automations/:id/events` - the automation's activity: one event
     * per person it answered, skipped or failed on. `outcome` filters on
     * `sent`, `skipped`, `failed`, `pending`, `queued` (waits for a free slot
     * under the send cap, out within the hour) or `opted_out` (the person
     * replied STOP on X); `limit` is 1 to 100 (default 25), `offset` skips. Requires the `automations:read` scope.
     *
     * The response is not the plain list envelope: `['data' => [...],
     * 'counts' => ['sent' => 12, 'skipped' => 3], 'pagination' => [...]]`,
     * where `counts` is the number of events per outcome (an outcome with no
     * events is left out). Each event carries `kind` (`comment`, `dm`,
     * `story_reply`), `outcome`, `reason` / `reason_label` (X adds the skip
     * reasons `dms_closed`, `no_credits`, `opted_out` and
     * `automation_off`), `person` (`id`,
     * `username`; on Facebook the person's name, since Facebook has no
     * @handle), `trigger_id`, `trigger_text`, `message` (the DM sent),
     * `public_reply`, `platform_message_id`, `created_at` and `sent_at`.
     *
     * @param array{outcome?: string, limit?: int, offset?: int} $params
     */
    public function listEvents(string $id, array $params = []): mixed
    {
        return $this->client->get('/automations/' . $this->encodePathSegment($id) . '/events', [
            'outcome' => $params['outcome'] ?? null,
            'limit' => $params['limit'] ?? null,
            'offset' => $params['offset'] ?? null,
        ]);
    }
}
