<?php

declare(strict_types=1);

namespace OmniSocials\Resource;

/**
 * Feature requests to the OmniSocials team, for the person who owns the key
 * (their name and email are stored with it so the team can answer). The
 * person sees them in OmniSocials (Settings, Request a feature) and gets an
 * email when the status changes. No scope needed.
 *
 * A request is `{ id, title, summary, status (open, in_progress, completed,
 * declined), team_answer, team_replies: [{ message, created_at }],
 * votes_count, limit_id, platform, area, source, created_at, updated_at,
 * completed_at }`.
 *
 * Error codes: `invalid_summary`, `invalid_title`, `invalid_platform`,
 * `invalid_area`, `unknown_limit`, `invalid_id` (400); `not_found` (404);
 * `not_requestable` (409: the `limit_id` is a platform rule or a choice of
 * OmniSocials, the entry is in `error.limit` of the body and nothing was
 * stored); `too_many_requests` (429, 10 an hour per person).
 */
class FeatureRequests extends AbstractResource
{
    /**
     * `POST /feature-requests` - send a request. Send it only when the person
     * asked for it. `summary` (required, 10 to 1000 characters) says what the
     * person wants to do and why; `title` (at most 120) is made from it when
     * left out; `limit_id` is the `id` of the `limits->list()` entry the
     * request is about. Returns `{ outcome, data }`: `outcome` is `created`
     * (a new request), `voted` (a vote added to the organisation's open
     * request for the same gap) or `already_requested` (this person already
     * has an open request for it; `data` is that request).
     *
     * @param array{summary: string, title?: string, platform?: string, area?: string, limit_id?: string} $params
     */
    public function create(array $params): mixed
    {
        return $this->client->post('/feature-requests', $params);
    }

    /**
     * `GET /feature-requests` - the person's own requests, newest first, at
     * most 50: `{ data: FeatureRequest[] }`.
     */
    public function list(): mixed
    {
        return $this->client->get('/feature-requests');
    }

    /**
     * `GET /feature-requests/:id` - one request with its status and the
     * team's replies.
     */
    public function get(string|int $id): mixed
    {
        return $this->client->get('/feature-requests/' . $this->encodePathSegment((string) $id));
    }
}
